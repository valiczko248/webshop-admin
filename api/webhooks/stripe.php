<?php
require __DIR__ . '/../../backend/webhook_auth.php';
require __DIR__ . '/../../backend/notifications.php';
require __DIR__ . '/../../backend/shipping.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Ismeretlen kérés.'], 400);

$payload = file_get_contents('php://input');
require_stripe_signature($payload);

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type'])) json_response(['error' => 'Érvénytelen esemény.'], 400);

$pdo = db();
$object = $event['data']['object'] ?? [];

// A rendelést elsősorban a PaymentIntent metadata.order_number mezője alapján azonosítjuk
// (ezt a webshopunk állítja be a fizetés indításakor) — ha az hiányzik, a payment intent
// ID-t (stripe_ref) próbáljuk párosítani, mert a demo-rendeléseknél is ez van eltárolva.
function find_order_for_stripe_object(PDO $pdo, array $object): ?array {
    $orderNumber = $object['metadata']['order_number'] ?? null;
    if ($orderNumber) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
        $stmt->execute([$orderNumber]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    $paymentIntentId = $object['payment_intent'] ?? $object['id'] ?? null;
    if ($paymentIntentId) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE stripe_ref = ?');
        $stmt->execute([$paymentIntentId]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    return null;
}

switch ($event['type']) {
    case 'payment_intent.succeeded':
        $order = find_order_for_stripe_object($pdo, $object);
        if ($order) {
            // Stripe újraküldheti ugyanazt az eseményt, ha a válaszunk nem érkezett meg időben —
            // $order itt még a FRISSÍTÉS ELŐTTI állapot, ezzel védjük ki a duplikált visszaigazoló
            // emailt egy megismételt kézbesítésnél.
            $alreadyPaid = $order['payment_status_label'] === 'Fizetve';

            $pdo->prepare("
                UPDATE orders SET payment_status_label = 'Fizetve', payment_status_class = 'pill pill--success',
                    payment_sync_note = NULL, stripe_ref = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ")->execute([$object['id'] ?? $order['stripe_ref'], $order['id']]);

            if (!$alreadyPaid) {
                $contact = find_order_contact($pdo, $order['order_number']);
                send_template_email('Rendelés visszaigazolás', $contact['email'], [
                    '{{vevo_nev}}' => $order['customer_name'],
                    '{{rendelesszam}}' => $order['order_number'],
                    '{{osszeg}}' => number_format((int) $order['amount'], 0, ',', ' ') . ' Ft',
                ]);
            }
            // A csomag-létrehozás saját maga is idempotens (kvikk_shipment_status = 'created' esetén
            // visszatér), ezért ez a hívás mindig biztonságos, retry esetén sem hoz létre duplikátumot.
            maybe_create_kvikk_shipment($pdo, (int) $order['id']);
        }
        break;

    case 'payment_intent.payment_failed':
        $order = find_order_for_stripe_object($pdo, $object);
        if ($order) {
            $alreadyFailed = $order['payment_status_label'] === 'Sikertelen fizetés';

            $pdo->prepare("
                UPDATE orders SET payment_status_label = 'Sikertelen fizetés', payment_status_class = 'pill pill--danger',
                    payment_sync_note = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ")->execute([$order['id']]);

            if (!$alreadyFailed) {
                $contact = find_order_contact($pdo, $order['order_number']);
                $storefrontUrl = get_setting('storefront_url');
                send_template_email('Fizetés sikertelen', $contact['email'], [
                    '{{vevo_nev}}' => $order['customer_name'],
                    '{{rendelesszam}}' => $order['order_number'],
                    // Ha nincs beállítva webshop URL (Fizetés oldal), a link helyén üresen marad —
                    // ez az egyetlen sablon, ami a saját storefrontodra mutató linket igényli.
                    '{{fizetes_link}}' => $storefrontUrl,
                ]);
            }
        }
        break;

    case 'charge.refunded':
        $order = find_order_for_stripe_object($pdo, $object);
        if ($order) {
            $returnStmt = $pdo->prepare('SELECT stripe_status FROM returns WHERE order_number = ?');
            $returnStmt->execute([$order['order_number']]);
            $alreadyRefunded = $returnStmt->fetchColumn() === 'refunded';

            // Csak akkor jelöljük visszatérítettnek a visszaküldést, ha már létezik hozzá
            // returns-sor (a Kvikk-visszaküldés regisztrálta) — anélkül nem tudjuk, fizikai
            // visszaküldésről vagy egyedi jóváírásról van-e szó, azt nem ez a webhook dönti el.
            $pdo->prepare("UPDATE returns SET stripe_status = 'refunded' WHERE order_number = ?")
                ->execute([$order['order_number']]);

            // A "Visszatérítés indítása" admin-gomb, amire a sablon eredetileg hivatkozott, soha
            // nem épült meg (a Visszaküldések oldal szándékosan csak olvasható — a Stripe/Kvikk
            // webhookok írják). Ez a valódi, megbízható jelzés arra, hogy a visszatérítés
            // ténylegesen megtörtént, ezért ide kötöttük be helyette. Csak az első feldolgozáskor
            // küldünk emailt, hogy egy Stripe-retry ne duplikálja azt.
            if (!$alreadyRefunded) {
                $contact = find_order_contact($pdo, $order['order_number']);
                send_template_email('Visszatérítés visszaigazolása', $contact['email'], [
                    '{{vevo_nev}}' => $order['customer_name'],
                    '{{rendelesszam}}' => $order['order_number'],
                    '{{osszeg}}' => number_format((int) $order['amount'], 0, ',', ' ') . ' Ft',
                ]);
            }
        }
        break;

    default:
        // Ismeretlen/nem kezelt esemény — nyugtázzuk, hogy Stripe ne próbálkozzon újra feleslegesen.
        break;
}

json_response(['ok' => true]);
