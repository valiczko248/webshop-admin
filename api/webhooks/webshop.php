<?php
require __DIR__ . '/../../backend/webhook_auth.php';
require __DIR__ . '/../../backend/inventory.php';
require __DIR__ . '/../../backend/notifications.php';
require __DIR__ . '/../../backend/shipping.php';
require_site_api_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Ismeretlen kérés.'], 400);

// A saját webshopunk hívja ezt meg, amikor egy vevő leadott egy rendelést — ez a szerződés,
// amit MI határozunk meg (nincs külső platform-elvárás, mint a Stripe/Kvikk webhookoknál).
$input = json_input();

// Adat-mezőleképezés (Beállítások oldal): ha a webshop más mezőnéven küldi ugyanazt az adatot
// (pl. "order_id" a "orderNumber" helyett), itt fordítjuk át a belső névre, még a kötelező mezők
// ellenőrzése előtt. Ha egy admin-változóhoz nincs leképezés megadva, vagy a belső név már eleve
// jelen van, nem nyúlunk hozzá.
$fieldMapping = json_decode(get_setting('field_mapping'), true) ?: [];
$mappingTargets = [
    'vevo_nev' => 'customerName',
    'rendelesszam' => 'orderNumber',
    'osszeg' => 'amount',
    'email' => 'customerEmail',
    'telefon' => 'customerPhone',
    'cim' => 'address',
];
foreach ($mappingTargets as $adminVar => $internalKey) {
    if (array_key_exists($internalKey, $input)) continue;
    $mappedFieldName = trim((string) ($fieldMapping[$adminVar] ?? ''));
    if ($mappedFieldName === '' || !array_key_exists($mappedFieldName, $input)) continue;
    $input[$internalKey] = $input[$mappedFieldName];
}

$required = ['orderNumber', 'customerName', 'amount', 'paymentMethod', 'items'];
foreach ($required as $field) {
    if (!array_key_exists($field, $input) || $input[$field] === '' || $input[$field] === null) {
        json_response(['error' => "Hiányzó mező: {$field}"], 400);
    }
}
// orderNumber/customerName kötelezően skalár (string/szám) legyen — ha a webshop pl. tömböt
// küldene ezekben, a lenti PDO-hívások egy nem kezelt TypeError-t dobnának (nyers 500-as hiba).
foreach (['orderNumber', 'customerName', 'paymentMethod'] as $field) {
    if (!is_scalar($input[$field])) {
        json_response(['error' => "A(z) {$field} mezőnek szövegnek/számnak kell lennie."], 400);
    }
}
if (!is_array($input['items']) || empty($input['items'])) {
    json_response(['error' => 'Legalább egy tétel szükséges.'], 400);
}

// Szigorú típus-/formátum-ellenőrzés, mielőtt bármi az adatbázisba kerülne — egy hibásan
// formázott webshop-payload (pl. hiányzó item-mező) enélkül egy nem kezelt PDO-hibával (NOT NULL
// megszorítás) egy nyers, információt szivárogtató 500-as hibát adna vissza. A Ft-nek nincs
// törtrésze ebben a rendszerben (mindenhol egész számként kezeljük) — egy "12.900"-szerű, ezres
// elválasztónak szánt érték is_numeric()-nek látszik (PHP 12,9-ként értelmezné), de (int)-re
// kasztolva csendben 12-re csonkulna, ezért az egész-szám-ellenőrzés itt kifejezetten kötelező.
function is_whole_nonneg_number($v): bool {
    return is_numeric($v) && (float) $v >= 0 && (float) $v == floor((float) $v);
}
if (!is_whole_nonneg_number($input['amount'])) {
    json_response(['error' => 'Az "amount" mezőnek nem negatív, egész Forint-összegnek kell lennie (nincs tizedesjegy vagy ezres elválasztó — pl. 12900, nem "12.900" vagy "12,900").'], 400);
}
$optionalScalarFields = [
    'customerEmail', 'customerPhone', 'address', 'courier', 'stripePaymentIntentId',
    'couponCode', 'addressLine', 'city', 'postcode', 'country', 'deliveryPointType', 'deliveryPointID',
];
foreach ($optionalScalarFields as $field) {
    if (isset($input[$field]) && !is_scalar($input[$field])) {
        json_response(['error' => "A(z) {$field} mezőnek szövegnek/számnak kell lennie."], 400);
    }
}
foreach ($input['items'] as $i => $item) {
    if (!is_array($item) || empty($item['name']) || !is_string($item['name'])) {
        json_response(['error' => "A(z) {$i}. tételnél hiányzik vagy érvénytelen a name mező."], 400);
    }
    if (!isset($item['qty']) || !is_whole_nonneg_number($item['qty']) || (int) $item['qty'] < 1) {
        json_response(['error' => "A(z) {$i}. tételnél a qty mezőnek pozitív egész számnak kell lennie."], 400);
    }
    if (!isset($item['unit']) || !is_whole_nonneg_number($item['unit'])) {
        json_response(['error' => "A(z) {$i}. tételnél a unit mezőnek nem negatív, egész Forint-összegnek kell lennie (tizedesjegy/ezres elválasztó nélkül)."], 400);
    }
}

// couponCode/orderNumber/customerName lehetnének nem-string típusok (pl. tömb) egy hibás
// payloadban — trim()/strtoupper() ezeken fatálisan elszállna, ezért itt normalizáljuk stringgé.
$couponCode = isset($input['couponCode']) && is_scalar($input['couponCode']) ? strtoupper(trim((string) $input['couponCode'])) : null;
if ($couponCode === '') $couponCode = null;

$pdo = db();

// Idempotens: ha ez a rendelésszám már létezik (pl. a webshop kétszer küldte el ugyanazt
// az eseményt újrapróbálkozáskor), nem hozunk létre duplikátumot, csak visszaadjuk a meglévőt.
$stmt = $pdo->prepare('SELECT id FROM orders WHERE order_number = ?');
$stmt->execute([$input['orderNumber']]);
if ($stmt->fetchColumn()) {
    json_response(['ok' => true, 'note' => 'Ez a rendelés már létezik, nem hoztunk létre újat.'], 200);
}

$paymentMethod = $input['paymentMethod'] === 'cod' ? 'cod' : 'card';
$isCod = $paymentMethod === 'cod';

$order = [
    'order_number' => $input['orderNumber'],
    'customer_name' => $input['customerName'],
    'amount' => (int) $input['amount'],
    'payment_method' => $paymentMethod,
    'payment_status_label' => $isCod ? 'Utánvét — kézbesítéskor' : 'Függőben',
    'payment_status_class' => $isCod ? 'pill pill--warn' : 'pill pill--warn',
    'payment_sync_note' => $isCod ? "A Kvikk 'delivered' webhookja fogja lezárni, amikor a futár átveszi a pénzt" : null,
    'shipping_status_label' => 'Csomagolás alatt',
    'shipping_status_class' => 'pill pill--warn',
    'shipping_sync_note' => null,
    'courier' => $input['courier'] ?? null,
    'order_date' => date('Y-m-d'),
    'order_time' => date('H:i'),
    'address' => $input['address'] ?? null,
    'stripe_ref' => $input['stripePaymentIntentId'] ?? null,
    'is_listed' => 1,
    'coupon_code' => $couponCode,
    // Tagolt cím — csak akkor kötelező, ha a webshop az Automata Kvikk-csomagolást is használja
    // (lásd backend/shipping.php); a fenti egybefűzött "address" a megjelenítéshez elég.
    'ship_address' => $input['addressLine'] ?? null,
    'ship_city' => $input['city'] ?? null,
    'ship_postcode' => $input['postcode'] ?? null,
    'ship_country' => $input['country'] ?? null,
    'delivery_point_type' => $input['deliveryPointType'] ?? null,
    'delivery_point_id' => $input['deliveryPointID'] ?? null,
];

// A fenti SELECT-es ellenőrzés csak a gyakori esetet gyorsítja — az igazi védelem az orders
// tábla UNIQUE(order_number) megszorítása. Ha két webhook-hívás (pl. egy időztúllépés miatti
// újrapróbálkozás) majdnem egyszerre fut le, mindkettő átcsúszhat a fenti SELECT-en, mielőtt
// bármelyik INSERT-elne — ez a catch fogja fel azt az esetet idempotensen, hiba helyett.
try {
    $result = with_transaction($pdo, function () use ($pdo, $order, $input, $couponCode) {
        $cols = array_keys($order);
        $stmt = $pdo->prepare('INSERT INTO orders (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_map(fn($c) => ":$c", $cols)) . ')');
        $stmt->execute($order);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_name, qty, unit_price) VALUES (?, ?, ?, ?)');
        foreach ($input['items'] as $item) {
            $itemStmt->execute([$orderId, $item['name'], (int) $item['qty'], (int) $item['unit']]);
        }

        // készlet lefoglalása (nem fizikai levonás — lásd backend/inventory.php)
        reserve_stock_for_order($pdo, array_map(fn($it) => ['product_name' => $it['name'], 'qty' => $it['qty']], $input['items']));

        // kupon-felhasználás rögzítése, ha a webshop küldött couponCode-ot
        if ($couponCode !== null) {
            $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?')
                ->execute([$couponCode]);
        }

        // vevő felvétele/frissítése — email alapján azonosítjuk az ismétlődő vásárlókat
        $email = $input['customerEmail'] ?? null;
        $customerId = null;
        if ($email) {
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
            $stmt->execute([$email]);
            $customerId = $stmt->fetchColumn() ?: null;
        }
        if (!$customerId) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $input['customerName']), '-')) ?: 'vevo';
            $baseSlug = $slug;
            $i = 2;
            while (true) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE customer_key = ?');
                $stmt->execute([$slug]);
                if ((int) $stmt->fetchColumn() === 0) break;
                $slug = $baseSlug . '-' . $i++;
            }
            $pdo->prepare('INSERT INTO customers (customer_key, name, email, phone) VALUES (?, ?, ?, ?)')
                ->execute([$slug, $input['customerName'], $email, $input['customerPhone'] ?? null]);
            $customerId = (int) $pdo->lastInsertId();
        }
        $productLabel = implode(', ', array_map(fn($it) => "{$it['name']} × {$it['qty']}", $input['items']));
        $pdo->prepare('
            INSERT INTO customer_purchases (customer_id, purchase_date, order_number, product_label, amount, status, stripe_ref)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $customerId, date('Y-m-d'), $input['orderNumber'], $productLabel,
            number_format((int) $input['amount'], 0, ',', ' ') . ' Ft',
            $isCod ? 'Függőben' : 'Függőben',
            $input['stripePaymentIntentId'] ?? null,
        ]);

        return $orderId;
    });
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'UNIQUE constraint failed: orders.order_number')) {
        json_response(['ok' => true, 'note' => 'Ez a rendelés már létezik, nem hoztunk létre újat.'], 200);
    }
    throw $e;
}

// Utánvétes rendelésnél nincs külön "sikeres fizetés" esemény (az a kézbesítéskor történik) —
// a visszaigazoló emailt ezért itt, rendelés-felvételkor küldjük. Kártyás rendelésnél ez a
// stripe.php webhook payment_intent.succeeded ágában megy ki, csak tényleges fizetés után.
if ($isCod) {
    send_template_email('Rendelés visszaigazolás', $input['customerEmail'] ?? null, [
        '{{vevo_nev}}' => $input['customerName'],
        '{{rendelesszam}}' => $input['orderNumber'],
        '{{osszeg}}' => number_format((int) $input['amount'], 0, ',', ' ') . ' Ft',
    ]);
    // Utánvétnél a fizetés a kézbesítéskor történik, nincs "sikeres fizetés" esemény, amire
    // várni lehetne — az automata Kvikk-csomagot ezért rögtön itt, felvételkor hozzuk létre.
    maybe_create_kvikk_shipment($pdo, $result);
}

json_response(['ok' => true, 'orderId' => $result], 201);
