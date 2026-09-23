<?php
require __DIR__ . '/../../backend/webhook_auth.php';
require __DIR__ . '/../../backend/inventory.php';
require __DIR__ . '/../../backend/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Ismeretlen kérés.'], 400);

// A valódi Kvikk webhook-séma szerint (https://support.kvikk.hu/docs/webhooks/payload,
// ellenőrizve 2026-09-23): az esemény típusa a kvikk-webhook-event FEJLÉCBEN jön (nem a
// body-ban), a body maga a teljes csomag-objektum, benne orderID-vel (ez a mi order_number-ünk)
// és courier mezővel. Támogatott eseménytípusok: dispatched, shipped, delivered, returned.
$payload = file_get_contents('php://input');
require_kvikk_signature($payload);

$event = $_SERVER['HTTP_KVIKK_WEBHOOK_EVENT'] ?? '';
$body = json_decode($payload, true);
if (!is_array($body) || empty($body['orderID']) || $event === '') {
    json_response(['error' => 'Hiányzó esemény-fejléc vagy orderID.'], 400);
}
$orderNumber = (string) $body['orderID'];

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();
if (!$order) json_response(['error' => 'Nincs ilyen rendelés.'], 404);

$kvikkNote = 'Automatikusan frissül a Kvikk webhookból';

switch ($event) {
    case 'dispatched':
    case 'shipped':
        // Kvikk (mint minden webhook-küldő) újraküldheti ugyanazt az eseményt, ha a válaszunk
        // nem érkezett meg időben — $order itt még a FRISSÍTÉS ELŐTTI állapot, ezért ezzel tudjuk
        // eldönteni, hogy ez az első feldolgozás-e. A release_stock_for_order() saját maga is
        // idempotens (stock_released flag), de az emailt csak most, explicit ellenőrzéssel védjük,
        // különben egy megismételt webhook duplikált "Feladva" levelet küldene a vevőnek.
        $alreadyShipped = $order['shipping_status_label'] === 'Feladva';

        $pdo->prepare("
            UPDATE orders SET shipping_status_label = 'Feladva', shipping_status_class = 'pill pill--neutral',
                shipping_sync_note = ?, courier = COALESCE(?, courier), updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ")->execute([$kvikkNote, $body['courier'] ?? null, $order['id']]);

        // A feladással a lefoglalt (reserved) készlet ténylegesen elhagyja a raktárt — most
        // csökkentjük a fizikai stock-ot is (lásd backend/inventory.php, idempotens).
        release_stock_for_order($pdo, (int) $order['id']);

        if (!$alreadyShipped) {
            $contact = find_order_contact($pdo, $orderNumber);
            send_template_email('Feladva értesítés', $contact['email'], [
                '{{vevo_nev}}' => $order['customer_name'],
                '{{rendelesszam}}' => $orderNumber,
                '{{nyomkovetesi_szam}}' => $body['trackingNumber'] ?? '',
            ]);
        }
        break;

    case 'delivered':
        $alreadyDelivered = $order['shipping_status_label'] === 'Kézbesítve';

        $sets = "shipping_status_label = 'Kézbesítve', shipping_status_class = 'pill pill--success', shipping_sync_note = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$kvikkNote];
        // Utánvétes rendelésnél a kézbesítés egyben az utánvét-beszedést is jelenti.
        if ($order['payment_method'] === 'cod') {
            $sets .= ", payment_status_label = 'Utánvét beszedve', payment_status_class = 'pill pill--success', payment_sync_note = ?";
            $params[] = "A Kvikk 'delivered' webhookja igazolja, mert kézbesítéskor vette át a futár a pénzt";
        }
        $params[] = $order['id'];
        $pdo->prepare("UPDATE orders SET $sets WHERE id = ?")->execute($params);

        // Idempotens védelem: egy megismételt "delivered" webhook (pl. Kvikk-újraküldés) ne
        // küldjön még egy kézbesítési emailt a vevőnek.
        if (!$alreadyDelivered) {
            $contact = find_order_contact($pdo, $orderNumber);
            send_template_email('Kézbesítve értesítés', $contact['email'], [
                '{{vevo_nev}}' => $order['customer_name'],
                '{{rendelesszam}}' => $orderNumber,
            ]);
        }
        break;

    case 'returned':
        $pdo->prepare("
            INSERT INTO returns (order_number, return_date, kvikk_status, stripe_status)
            VALUES (?, ?, 'returned', 'pending')
            ON CONFLICT(order_number) DO UPDATE SET kvikk_status = 'returned'
        ")->execute([$orderNumber, date('Y-m-d')]);
        break;

    default:
        json_response(['error' => "Ismeretlen event: {$event}"], 400);
}

json_response(['ok' => true]);
