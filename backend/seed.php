<?php
// Csak első indításkor fut le (amikor még nincs adatbázis-fájl), hogy a backend
// ugyanazokkal a demó-termékekkel induljon, mint korábban a statikus mockup.

function seed_database(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO products
            (type, name, price, weight, stock, reserved, deduction, linked_name, linked_qty, linked_weight, gallery, variants, addons, archived)
        VALUES
            (:type, :name, :price, :weight, :stock, :reserved, :deduction, :linked_name, :linked_qty, :linked_weight, :gallery, :variants, :addons, 0)
    ');

    $products = [
        [
            'type' => 'regular',
            'name' => 'Alaptermék',
            'price' => 12900,
            'weight' => 250,
            'stock' => 48,
            'reserved' => 7,
            'deduction' => 1,
            'linked_name' => 'Doboz',
            'linked_qty' => 1,
            'linked_weight' => 35,
            'gallery' => '[]',
            'variants' => '[]',
            'addons' => '[]',
        ],
        [
            'type' => 'regular',
            'name' => 'Nyisd Ki Amikor — alapcsomag',
            'price' => 4990,
            'weight' => 15,
            'stock' => 43,
            'reserved' => 5,
            'deduction' => 1,
            'linked_name' => null,
            'linked_qty' => null,
            'linked_weight' => null,
            'gallery' => '[]',
            'variants' => json_encode([
                ['name' => 'Piros boríték', 'stock' => 15, 'weight' => 15],
                ['name' => 'Fehér boríték', 'stock' => 20, 'weight' => 15],
                ['name' => 'Fekete boríték', 'stock' => 8, 'weight' => 15],
            ], JSON_UNESCAPED_UNICODE),
            'addons' => json_encode([
                ['name' => 'QR-kódos hangüzenet', 'price' => 990, 'weight' => '', 'linked' => '', 'linkedQty' => ''],
                ['name' => 'Masnikötés', 'price' => 390, 'weight' => 5, 'linked' => 'Doboz', 'linkedQty' => 1],
            ], JSON_UNESCAPED_UNICODE),
        ],
        [
            'type' => 'component',
            'name' => 'Doboz',
            'price' => null,
            'weight' => 35,
            'stock' => 120,
            'reserved' => 7,
            'deduction' => 1,
            'linked_name' => null,
            'linked_qty' => null,
            'linked_weight' => null,
            'gallery' => '[]',
            'variants' => '[]',
            'addons' => '[]',
        ],
    ];

    foreach ($products as $p) {
        $stmt->execute($p);
    }

    seed_orders($pdo);
    seed_customers($pdo);
    seed_returns($pdo);
    seed_coupons($pdo);
    seed_settings($pdo);
    seed_email_templates($pdo);
    seed_legal_docs($pdo);
    seed_gdpr_requests($pdo);
}

function seed_orders(PDO $pdo): void {
    $orderStmt = $pdo->prepare('
        INSERT INTO orders
            (order_number, customer_name, amount, payment_method, payment_status_label, payment_status_class, payment_sync_note,
             shipping_status_label, shipping_status_class, shipping_sync_note, invoiced, invoice_file, courier,
             order_date, order_time, address, stripe_ref, is_listed)
        VALUES
            (:order_number, :customer_name, :amount, :payment_method, :payment_status_label, :payment_status_class, :payment_sync_note,
             :shipping_status_label, :shipping_status_class, :shipping_sync_note, :invoiced, :invoice_file, :courier,
             :order_date, :order_time, :address, :stripe_ref, :is_listed)
    ');
    $itemStmt = $pdo->prepare('
        INSERT INTO order_items (order_id, product_name, qty, unit_price) VALUES (:order_id, :product_name, :qty, :unit_price)
    ');

    $kvikkAutoNote = "Automatikusan frissül a Kvikk webhookból";

    $orders = [
        [
            'order_number' => '0231', 'customer_name' => 'Kovács Anna', 'amount' => 12900, 'payment_method' => 'card',
            'payment_status_label' => 'Fizetve', 'payment_status_class' => 'pill pill--success', 'payment_sync_note' => null,
            'shipping_status_label' => 'Csomagolás alatt', 'shipping_status_class' => 'pill pill--warn', 'shipping_sync_note' => null,
            'invoiced' => 0, 'invoice_file' => null, 'courier' => 'foxpost',
            'order_date' => '2026-09-20', 'order_time' => '09:14',
            'address' => 'Kovács Anna, 1051 Budapest, Váci utca 10.', 'stripe_ref' => 'pi_3PtestAB12cKovacs', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 12900]],
        ],
        [
            'order_number' => '0230', 'customer_name' => 'Nagy Bence', 'amount' => 9400, 'payment_method' => 'card',
            'payment_status_label' => 'Fizetve', 'payment_status_class' => 'pill pill--success', 'payment_sync_note' => null,
            'shipping_status_label' => 'Feladva', 'shipping_status_class' => 'pill pill--neutral', 'shipping_sync_note' => $kvikkAutoNote,
            'invoiced' => 1, 'invoice_file' => 'szamla_0230.pdf', 'courier' => 'mpl',
            'order_date' => '2026-09-19', 'order_time' => '14:02',
            'address' => 'Nagy Bence, 4024 Debrecen, Piac utca 5.', 'stripe_ref' => 'pi_3PtestCD34eNagyB', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 9400]],
        ],
        [
            'order_number' => '0229', 'customer_name' => 'Szabó Petra', 'amount' => 21800, 'payment_method' => 'card',
            'payment_status_label' => 'Függőben', 'payment_status_class' => 'pill pill--warn', 'payment_sync_note' => null,
            'shipping_status_label' => 'Vár fizetésre', 'shipping_status_class' => 'pill pill--warn', 'shipping_sync_note' => null,
            'invoiced' => 0, 'invoice_file' => null, 'courier' => 'gls',
            'order_date' => '2026-09-19', 'order_time' => '19:47',
            'address' => 'Szabó Petra, 6720 Szeged, Kárász utca 3.', 'stripe_ref' => '— (fizetésre vár)', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 2, 'unit' => 10900]],
        ],
        [
            'order_number' => '0228', 'customer_name' => 'Tóth Dávid', 'amount' => 12900, 'payment_method' => 'cod',
            'payment_status_label' => 'Utánvét beszedve', 'payment_status_class' => 'pill pill--success',
            'payment_sync_note' => "A Kvikk 'delivered' webhookja igazolja, mert kézbesítéskor vette át a futár a pénzt",
            'shipping_status_label' => 'Kézbesítve', 'shipping_status_class' => 'pill pill--success', 'shipping_sync_note' => $kvikkAutoNote,
            'invoiced' => 1, 'invoice_file' => null, 'courier' => 'packeta',
            'order_date' => '2026-09-18', 'order_time' => '11:23',
            'address' => 'Tóth Dávid, 9021 Győr, Baross Gábor út 15.',
            'stripe_ref' => '12 900 Ft — futár beszedte, kézbesítéskor (Kvikk visszaigazolva)', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 12900]],
        ],
        [
            'order_number' => '0226', 'customer_name' => 'Kiss Gábor', 'amount' => 8900, 'payment_method' => 'cod',
            'payment_status_label' => 'Utánvét — kézbesítéskor', 'payment_status_class' => 'pill pill--warn',
            'payment_sync_note' => "A Kvikk 'delivered' webhookja fogja lezárni, amikor a futár átveszi a pénzt",
            'shipping_status_label' => 'Feladva', 'shipping_status_class' => 'pill pill--neutral', 'shipping_sync_note' => $kvikkAutoNote,
            'invoiced' => 0, 'invoice_file' => null, 'courier' => 'dpd',
            'order_date' => '2026-09-17', 'order_time' => '16:58',
            'address' => 'Kiss Gábor, 3300 Eger, Dobó tér 2.', 'stripe_ref' => '8 900 Ft — a futár szedi be kézbesítéskor', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 8900]],
        ],
        [
            'order_number' => '0227', 'customer_name' => 'Horváth Eszter', 'amount' => 6200, 'payment_method' => 'card',
            'payment_status_label' => 'Lemondva', 'payment_status_class' => 'pill pill--danger', 'payment_sync_note' => null,
            'shipping_status_label' => '—', 'shipping_status_class' => 'pill pill--danger', 'shipping_sync_note' => null,
            'invoiced' => 0, 'invoice_file' => null, 'courier' => 'foxpost',
            'order_date' => '2026-09-17', 'order_time' => '10:05',
            'address' => 'Horváth Eszter, 7621 Pécs, Rákóczi út 8.', 'stripe_ref' => '— (lemondva)', 'is_listed' => 1,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 6200]],
        ],
        // régebbi, csak a Visszaküldések oldalról hivatkozott rendelések — a fő listában nem jelennek meg
        [
            'order_number' => '0212', 'customer_name' => 'Teszt Judit', 'amount' => 9800, 'payment_method' => 'card',
            'payment_status_label' => 'Fizetve', 'payment_status_class' => 'pill pill--success', 'payment_sync_note' => null,
            'shipping_status_label' => 'Visszaküldve', 'shipping_status_class' => 'pill pill--success', 'shipping_sync_note' => null,
            'invoiced' => 1, 'invoice_file' => null, 'courier' => null,
            'order_date' => '2026-09-10', 'order_time' => null,
            'address' => 'Teszt Judit, 2000 Szentendre, Fő tér 1.', 'stripe_ref' => 'pi_3PtestEF11jTesztJ', 'is_listed' => 0,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 9800]],
        ],
        [
            'order_number' => '0205', 'customer_name' => 'Varga Zsolt', 'amount' => 12900, 'payment_method' => 'card',
            'payment_status_label' => 'Fizetve', 'payment_status_class' => 'pill pill--success', 'payment_sync_note' => null,
            'shipping_status_label' => 'Visszaküldve', 'shipping_status_class' => 'pill pill--success', 'shipping_sync_note' => null,
            'invoiced' => 1, 'invoice_file' => null, 'courier' => null,
            'order_date' => '2026-09-15', 'order_time' => null,
            'address' => 'Varga Zsolt, 8000 Székesfehérvár, Városház tér 3.', 'stripe_ref' => 'pi_3PtestGH22kVargaZs', 'is_listed' => 0,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 12900]],
        ],
        [
            'order_number' => '0193', 'customer_name' => 'Fekete Márk', 'amount' => 6200, 'payment_method' => 'card',
            'payment_status_label' => 'Fizetve', 'payment_status_class' => 'pill pill--success', 'payment_sync_note' => null,
            'shipping_status_label' => 'Úton visszafelé', 'shipping_status_class' => 'pill pill--warn', 'shipping_sync_note' => null,
            'invoiced' => 1, 'invoice_file' => null, 'courier' => null,
            'order_date' => '2026-09-18', 'order_time' => null,
            'address' => 'Fekete Márk, 7100 Szekszárd, Béla király tér 6.',
            'stripe_ref' => '— (visszaküldés úton, a visszatérítés még nem esedékes)', 'is_listed' => 0,
            'items' => [['name' => 'Alaptermék', 'qty' => 1, 'unit' => 6200]],
        ],
    ];

    foreach ($orders as $o) {
        $items = $o['items'];
        unset($o['items']);
        $orderStmt->execute($o);
        $orderId = (int) $pdo->lastInsertId();
        foreach ($items as $item) {
            $itemStmt->execute([
                'order_id' => $orderId,
                'product_name' => $item['name'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit'],
            ]);
        }
    }
}

function seed_customers(PDO $pdo): void {
    $customerStmt = $pdo->prepare('INSERT INTO customers (customer_key, name, email, phone) VALUES (:customer_key, :name, :email, :phone)');
    $purchaseStmt = $pdo->prepare('
        INSERT INTO customer_purchases (customer_id, purchase_date, order_number, product_label, amount, status, stripe_ref)
        VALUES (:customer_id, :purchase_date, :order_number, :product_label, :amount, :status, :stripe_ref)
    ');

    $customers = [
        [
            'customer_key' => 'kovacs-anna', 'name' => 'Kovács Anna', 'email' => 'kovacs.anna@email.hu', 'phone' => '+36 30 123 4567',
            'purchases' => [
                ['date' => '2026-09-20', 'order' => '#0231', 'product' => 'Alaptermék × 1', 'amount' => '12 900 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestAB12cKovacs'],
                ['date' => '2026-08-02', 'order' => '#0198', 'product' => 'Alaptermék × 1', 'amount' => '9 800 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestQR56sKovacs'],
                ['date' => '2026-06-14', 'order' => '#0142', 'product' => 'Alaptermék × 2', 'amount' => '15 200 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestST78uKovacs'],
            ],
        ],
        [
            'customer_key' => 'nagy-bence', 'name' => 'Nagy Bence', 'email' => 'nagy.bence@email.hu', 'phone' => '+36 20 987 6543',
            'purchases' => [
                ['date' => '2026-09-19', 'order' => '#0230', 'product' => 'Alaptermék × 1', 'amount' => '9 400 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestCD34eNagyB'],
            ],
        ],
        [
            'customer_key' => 'szabo-petra', 'name' => 'Szabó Petra', 'email' => 'szabo.petra@email.hu', 'phone' => '+36 70 555 1122',
            'purchases' => [
                ['date' => '2026-09-19', 'order' => '#0229', 'product' => 'Alaptermék × 2', 'amount' => '21 800 Ft', 'status' => 'Függőben', 'stripeRef' => null],
                ['date' => '2026-08-10', 'order' => '#0201', 'product' => 'Alaptermék × 1', 'amount' => '12 900 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestUV90wSzaboP'],
                ['date' => '2026-07-22', 'order' => '#0176', 'product' => 'Alaptermék × 1', 'amount' => '9 400 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestWX12ySzaboP'],
                ['date' => '2026-06-30', 'order' => '#0150', 'product' => 'Alaptermék × 1', 'amount' => '6 200 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestYZ34aSzaboP'],
                ['date' => '2026-06-02', 'order' => '#0133', 'product' => 'Alaptermék × 1', 'amount' => '12 900 Ft', 'status' => 'Fizetve', 'stripeRef' => 'pi_3PtestBC56dSzaboP'],
            ],
        ],
    ];

    foreach ($customers as $c) {
        $purchases = $c['purchases'];
        unset($c['purchases']);
        $customerStmt->execute($c);
        $customerId = (int) $pdo->lastInsertId();
        foreach ($purchases as $p) {
            $purchaseStmt->execute([
                'customer_id' => $customerId,
                'purchase_date' => $p['date'],
                'order_number' => $p['order'],
                'product_label' => $p['product'],
                'amount' => $p['amount'],
                'status' => $p['status'],
                'stripe_ref' => $p['stripeRef'],
            ]);
        }
    }
}

function seed_returns(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO returns (order_number, return_date, kvikk_status, stripe_status)
        VALUES (:order_number, :return_date, :kvikk_status, :stripe_status)
    ');
    $returns = [
        ['order_number' => '0212', 'return_date' => '2026-09-10', 'kvikk_status' => 'returned', 'stripe_status' => 'refunded'],
        ['order_number' => '0205', 'return_date' => '2026-09-15', 'kvikk_status' => 'returned', 'stripe_status' => 'pending'],
        ['order_number' => '0193', 'return_date' => '2026-09-18', 'kvikk_status' => 'intransit', 'stripe_status' => 'na'],
    ];
    foreach ($returns as $r) {
        $stmt->execute($r);
    }
}

function seed_coupons(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO coupons
            (code, discount_type, discount_value, start_now, from_date, until_date, usage_limit, used_count, limit_per_email, status, note, excluded_products)
        VALUES
            (:code, :discount_type, :discount_value, :start_now, :from_date, :until_date, :usage_limit, :used_count, :limit_per_email, :status, :note, :excluded_products)
    ');
    $coupons = [
        [
            'code' => 'OSSZ10', 'discount_type' => 'percent', 'discount_value' => 10, 'start_now' => 1,
            'from_date' => null, 'until_date' => '2026-10-01', 'usage_limit' => 100, 'used_count' => 14,
            'limit_per_email' => null, 'status' => 'success',
            'note' => 'Ősszel indított IG-kampányhoz, csak story-linkből érhető el.',
            'excluded_products' => json_encode(['Doboz'], JSON_UNESCAPED_UNICODE),
        ],
        [
            'code' => 'ELSOVEVO', 'discount_type' => 'fixed', 'discount_value' => 1500, 'start_now' => 1,
            'from_date' => null, 'until_date' => null, 'usage_limit' => null, 'used_count' => 62,
            'limit_per_email' => 1, 'status' => 'success', 'note' => null,
            'excluded_products' => '[]',
        ],
        [
            'code' => 'NYAR2026', 'discount_type' => 'percent', 'discount_value' => 15, 'start_now' => 1,
            'from_date' => null, 'until_date' => '2026-08-31', 'usage_limit' => 300, 'used_count' => 208,
            'limit_per_email' => null, 'status' => 'danger', 'note' => null,
            'excluded_products' => '[]',
        ],
    ];
    foreach ($coupons as $c) {
        $stmt->execute($c);
    }
}

function seed_settings(PDO $pdo): void {
    $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)');
    $settings = [
        'email_sender_name' => 'Webshop',
        'email_sender_address' => 'rendeles@webshop.hu',
        // a webshop → backend webhook (új rendelés) ezt várja az X-API-Key fejlécben
        'site_api_key' => 'whs_live_' . bin2hex(random_bytes(12)),
        // üresen indul — csak akkor kötelező kitölteni, ha valódi Stripe/Kvikk webhookot kötsz be
        'stripe_webhook_secret' => '',
        'kvikk_webhook_secret' => '',
        // admin-változó => a webshopod saját mezőneve (Beállítások oldal, "Adat-mezőleképezés")
        'field_mapping' => '',
        // alapból kikapcsolva — a Szállítás oldal "Automata" kapcsolójával élesíthető
        'kvikk_auto_mode' => '0',
        'kvikk_default_courier' => '',
        'storefront_url' => '',
        // valódi Stripe API-hívásokhoz (kulcs-ellenőrzés, Rendszerteszt élő fizetése) — sosem kerül
        // vissza a böngészőbe máshogy, csak a saját, jelszó-mezős beviteli mezőbe visszaolvasva
        'stripe_test_secret_key' => '',
        'stripe_test_publishable_key' => '',
        'stripe_live_secret_key' => '',
        'stripe_live_publishable_key' => '',
        'last_webshop_call_at' => '',
    ];
    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
}

function seed_email_templates(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO email_templates (name, trigger_label, subject, body, status)
        VALUES (:name, :trigger_label, :subject, :body, :status)
    ');
    $templates = [
        [
            'name' => 'Rendelés visszaigazolás', 'trigger_label' => 'Sikeres fizetés után', 'status' => 'success',
            'subject' => 'Köszönjük a rendelésed! (#{{rendelesszam}})',
            'body' => "Kedves {{vevo_nev}}!\n\nKöszönjük a rendelésed, sikeresen megérkezett a fizetés. Rendelésed adatai:\n\nRendelésszám: {{rendelesszam}}\nÖsszeg: {{osszeg}}\n\nHamarosan csomagoljuk és feladjuk a rendelésed, erről is kapsz majd egy emailt.\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'Feladva értesítés', 'trigger_label' => 'Kvikk: "shipped" esemény', 'status' => 'success',
            'subject' => 'Feladtuk a csomagod! (#{{rendelesszam}})',
            'body' => "Kedves {{vevo_nev}}!\n\nCsomagod feladtuk, hamarosan megérkezik. Nyomkövetési szám: {{nyomkovetesi_szam}}\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'Kézbesítve értesítés', 'trigger_label' => 'Kvikk: "delivered" esemény', 'status' => 'neutral',
            'subject' => 'Kézbesítették a csomagod (#{{rendelesszam}})',
            'body' => "Kedves {{vevo_nev}}!\n\nA futár kézbesítette a csomagod. Reméljük, elégedett leszel a rendeléseddel!\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'Kosár-emlékeztető', 'trigger_label' => 'Kosárban hagyott termék, 24 óra után', 'status' => 'neutral',
            'subject' => 'Elfelejtettél valamit a kosaradban',
            'body' => "Kedves {{vevo_nev}}!\n\nÚgy vettük észre, kosárban hagytál egy terméket. Ha még gondolkozol rajta, itt egy emlékeztető — a kosarad vár rád.\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'Fizetés sikertelen', 'trigger_label' => 'Stripe: elutasított fizetés', 'status' => 'success',
            'subject' => 'Sikertelen fizetés — próbáld újra (#{{rendelesszam}})',
            'body' => "Kedves {{vevo_nev}}!\n\nSajnos a fizetésed nem sikerült (a bankod elutasította vagy hiba történt). A rendelésed még nincs törölve — az alábbi linken újra megpróbálhatod a fizetést:\n\n{{fizetes_link}}\n\nHa kérdésed van, válaszolj erre az emailre.\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'Visszatérítés visszaigazolása', 'trigger_label' => 'Admin: "Visszatérítés indítása" gomb', 'status' => 'success',
            'subject' => 'Visszatérítést indítottunk (#{{rendelesszam}})',
            'body' => "Kedves {{vevo_nev}}!\n\nMegkezdtük a {{osszeg}} összegű visszatérítésed feldolgozását. A pénz 5-10 munkanapon belül megjelenik a bankszámládon, arra a kártyára, amivel fizettél.\n\nÜdvözlettel,\nWebshop csapata",
        ],
        [
            'name' => 'GDPR-kérelem teljesítve', 'trigger_label' => 'Admin: GDPR-kérelem "Teljesítve" gomb', 'status' => 'success',
            'subject' => 'A GDPR-kérésedet teljesítettük',
            'body' => "Kedves Ügyfelünk!\n\nTájékoztatunk, hogy a személyes adataiddal kapcsolatos kérésedet (adatexport vagy törlés) teljesítettük.\n\nHa bármi kérdésed van az adatkezeléssel kapcsolatban, keress minket bizalommal.\n\nÜdvözlettel,\nWebshop csapata",
        ],
    ];
    foreach ($templates as $t) {
        $stmt->execute($t);
    }
}

function seed_legal_docs(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO legal_docs (name, url, effective_date, status, content, archived)
        VALUES (:name, :url, :effective_date, :status, :content, 0)
    ');
    $docs = [
        [
            'name' => 'Impresszum', 'url' => 'https://webshop.hu/impresszum', 'effective_date' => '2026-09-01', 'status' => 'success',
            'content' => "Cégnév: Webshop Kft.\nSzékhely: 1000 Budapest, Példa utca 1.\nAdószám: 12345678-1-42\nCégjegyzékszám: 01-09-000000\nTárhelyszolgáltató: Rackhost Zrt.\nElérhetőség: rendeles@webshop.hu",
        ],
        [
            'name' => 'ÁSZF', 'url' => 'https://webshop.hu/aszf', 'effective_date' => '2026-09-01', 'status' => 'success',
            'content' => "Jelen Általános Szerződési Feltételek a Webshop.hu oldalon leadott rendelésekre vonatkoznak.\n\n1. A szerződő felek\n2. Rendelés menete\n3. Fizetési módok\n4. Szállítási feltételek\n5. Elállási jog\n6. Panaszkezelés",
        ],
        [
            'name' => 'Adatkezelési tájékoztató', 'url' => 'https://webshop.hu/adatkezelesi-tajekoztato', 'effective_date' => '2026-09-01', 'status' => 'success',
            'content' => "A Webshop.hu elkötelezett a vásárlói adatok védelme mellett. Ez a tájékoztató bemutatja, milyen adatokat kezelünk, milyen célból, és milyen jogok illetnek meg az adataid kapcsán (GDPR).\n\nKezelt adatok: név, email, telefonszám, szállítási cím.\nCélja: rendelés teljesítése, számlázás, kapcsolattartás.\nJogalap: szerződés teljesítése.\nAdatkezelés időtartama: a törvényi megőrzési idő lejártáig.",
        ],
        [
            'name' => 'Cookie szabályzat', 'url' => 'https://webshop.hu/cookie-szabalyzat', 'effective_date' => '2026-09-05', 'status' => 'neutral',
            'content' => "Az oldal sütiket (cookie-kat) használ a működéshez, valamint — hozzájárulásod esetén — analitikai és marketing célból (Google Analytics, Meta Pixel, Microsoft Clarity).\n\nSzükséges sütik: mindig aktívak, a kosár és bejelentkezés működéséhez kellenek.\nAnalitikai sütik: csak hozzájárulás után aktiválódnak.\nA hozzájárulásod bármikor visszavonhatod.",
        ],
        [
            'name' => 'Elállási nyilatkozat', 'url' => 'https://webshop.hu/elallasi-nyilatkozat', 'effective_date' => '2026-09-01', 'status' => 'success',
            'content' => "Tájékoztatjuk, hogy a fogyasztót a 45/2014. (II. 26.) Korm. rendelet (és az azt módosító, 2022-től hatályos Omnibus-implementáció) alapján a termék kézhezvételétől számított 14 napon belül indoklás nélküli elállási jog illeti meg.\n\nAz elállási jog gyakorlása: a vevő elállási szándékát egyértelmű nyilatkozattal (postán vagy emailben) köteles jelezni — ehhez felhasználhatja az alábbi, jogszabály szerinti nyilatkozatmintát is, de ez nem kötelező forma.\n\nA visszaküldés módja: a termék visszaküldése a Kvikk visszaküldési portálján keresztül generált címkével történik — ez a fizikai/logisztikai lépés, amit a Kvikk biztosít, a jelen jogi nyilatkozat viszont ettől független, ezt a törvény írja elő kötelezően.\nA visszatérítés: a termék visszaérkezését követően, a vételár (és az alapértelmezett szállítási költség) 14 napon belül kerül visszautalásra a Stripe-on keresztül.\n\n—\n\nELÁLLÁSI/FELMONDÁSI NYILATKOZATMINTA\n(csak a szerződéstől való elállási/felmondási szándék esetén töltse ki és juttassa vissza)\n\nCímzett: Webshop Kft., 1000 Budapest, Példa utca 1., rendeles@webshop.hu\n\nAlulírott kijelentem, hogy gyakorlom elállási jogomat az alábbi termék/ek adásvételére irányuló szerződés tekintetében:\n\nSzerződéskötés időpontja / átvétel időpontja:\nA fogyasztó neve:\nA fogyasztó címe:\nA fogyasztó aláírása (kizárólag papíron tett nyilatkozat esetén):\nKelt:",
        ],
    ];
    foreach ($docs as $d) {
        $stmt->execute($d);
    }
}

function seed_gdpr_requests(PDO $pdo): void {
    $stmt = $pdo->prepare('
        INSERT INTO gdpr_requests (email, request_type, received_date, status)
        VALUES (:email, :request_type, :received_date, :status)
    ');
    $requests = [
        ['email' => 'kovacs.anna@email.hu', 'request_type' => 'Adatexport', 'received_date' => '2026-09-18', 'status' => 'warn'],
        ['email' => 'regi.vevo@email.hu', 'request_type' => 'Törlési kérelem', 'received_date' => '2026-09-10', 'status' => 'success'],
    ];
    foreach ($requests as $r) {
        $stmt->execute($r);
    }
}
