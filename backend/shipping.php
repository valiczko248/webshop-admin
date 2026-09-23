<?php
require_once __DIR__ . '/kvikk_client.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/webhook_auth.php'; // get_setting()

const KVIKK_KNOWN_COURIERS = ['mpl', 'foxpost', 'packeta', 'famafutar', 'dpd', 'gls'];

function mark_kvikk_shipment_error(PDO $pdo, int $orderId, string $message): void {
    $pdo->prepare('
        UPDATE orders SET kvikk_shipment_status = ?, kvikk_shipment_error = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ')->execute(['error', $message, $orderId]);
}

// Automata mód: fizetés (kártya) vagy rendelés-felvétel (utánvét) után meghívja a Kvikk
// POST /v1/shipment végpontját. Csendben visszatér (nem dob hibát), ha az Automata mód ki van
// kapcsolva, vagy ha a rendeléshez már létrejött csomag — minden más hibaesetet az orders
// kvikk_shipment_error mezőjén jelez, hogy az admin lássa és kézzel pótolhassa a Kvikk oldalán.
function maybe_create_kvikk_shipment(PDO $pdo, int $orderId): void {
    if (get_setting('kvikk_auto_mode') !== '1') return;

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || $order['kvikk_shipment_status'] === 'created') return;

    $contactStmt = $pdo->prepare('
        SELECT c.email, c.phone FROM customers c
        JOIN customer_purchases cp ON cp.customer_id = c.id
        WHERE cp.order_number = ? LIMIT 1
    ');
    $contactStmt->execute([$order['order_number']]);
    $contact = $contactStmt->fetch() ?: ['email' => null, 'phone' => null];

    if (empty($contact['email']) || empty($contact['phone'])) {
        mark_kvikk_shipment_error($pdo, $orderId, 'Hiányzó vevő-email vagy telefonszám — a Kvikk mindkettőt kötelezően kéri a csomag létrehozásához.');
        return;
    }

    $weight = compute_order_weight_grams($pdo, $orderId);
    if ($weight === null) {
        mark_kvikk_shipment_error($pdo, $orderId, 'Hiányzó termék-súly — minden eladott tételnél meg kell adni a súlyt a Termékeknél (missing_weight lenne a Kvikk API válasza).');
        return;
    }

    $courier = strtolower((string) $order['courier']);
    if (!in_array($courier, KVIKK_KNOWN_COURIERS, true)) {
        $courier = strtolower(get_setting('kvikk_default_courier'));
    }
    if (!in_array($courier, KVIKK_KNOWN_COURIERS, true)) {
        mark_kvikk_shipment_error($pdo, $orderId, 'Nincs érvényes Kvikk futár megadva ehhez a rendeléshez, és nincs alapértelmezett futár beállítva a Szállítás oldalon.');
        return;
    }

    $senderID = kvikk_pick_sender_id();
    if (!$senderID) {
        mark_kvikk_shipment_error($pdo, $orderId, 'Nem sikerült lekérni a Kvikk feladó-azonosítót (senderID) — ellenőrizd a Kvikk-kapcsolatot a Szállítás oldalon.');
        return;
    }

    $payload = [
        'name' => $order['customer_name'],
        'phone' => $contact['phone'],
        'email' => $contact['email'],
        'courier' => $courier,
        'orderID' => $order['order_number'],
        'parcels' => [[
            'weight' => $weight,
            'value' => (int) $order['amount'],
        ]],
        'cod' => $order['payment_method'] === 'cod' ? (int) $order['amount'] : 0,
        'senderID' => $senderID,
    ];

    if (!empty($order['delivery_point_type']) && !empty($order['delivery_point_id'])) {
        $payload['deliveryPointType'] = $order['delivery_point_type'];
        $payload['deliveryPointID'] = $order['delivery_point_id'];
    } else {
        if (empty($order['ship_address']) || empty($order['ship_city']) || empty($order['ship_postcode'])) {
            mark_kvikk_shipment_error($pdo, $orderId, 'Hiányzó tagolt cím (utca/város/irányítószám) — a webshopnak külön mezőkben (addressLine/city/postcode) is el kell küldenie ezt az automata Kvikk-csomaghoz, az egybefűzött "address" mező nem elég.');
            return;
        }
        $payload['address'] = $order['ship_address'];
        $payload['city'] = $order['ship_city'];
        $payload['postcode'] = $order['ship_postcode'];
        $payload['country'] = $order['ship_country'] ?: 'HU';
    }

    $res = kvikk_create_shipment($payload);
    if (!$res['ok']) {
        mark_kvikk_shipment_error($pdo, $orderId, $res['error']);
        return;
    }

    $data = $res['data']['data'] ?? [];
    $trackingNumber = $data['trackingNumber'] ?? null;
    $managementLink = $data['accounting']['link'] ?? null;

    $labelStoredName = null;
    if (!empty($data['label'])) {
        $bytes = base64_decode($data['label'], true);
        if ($bytes !== false) {
            $labelDir = __DIR__ . '/../uploads/labels';
            if (!is_dir($labelDir)) mkdir($labelDir, 0777, true);
            $labelStoredName = bin2hex(random_bytes(16)) . '.pdf';
            file_put_contents($labelDir . '/' . $labelStoredName, $bytes);
        }
    }

    $pdo->prepare('
        UPDATE orders SET
            kvikk_shipment_status = ?, kvikk_tracking_number = ?, kvikk_tracking_link = ?,
            kvikk_label_stored_name = ?, kvikk_shipment_error = NULL,
            shipping_status_label = ?, shipping_status_class = ?, shipping_sync_note = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ')->execute([
        'created', $trackingNumber, $managementLink, $labelStoredName,
        'Csomag létrehozva (Kvikk)', 'pill pill--neutral',
        'Automatikusan létrehozva a Kvikk API-n keresztül (Automata mód)',
        $orderId,
    ]);
}
