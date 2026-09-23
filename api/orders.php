<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$number = isset($_GET['number']) ? $_GET['number'] : null;

function order_row_to_json(PDO $pdo, array $row): array {
    $itemsStmt = $pdo->prepare('SELECT product_name, qty, unit_price FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemsStmt->execute([$row['id']]);
    $items = array_map(function ($it) {
        return [
            'name' => $it['product_name'],
            'qty' => (int) $it['qty'],
            'unit' => (int) $it['unit_price'],
        ];
    }, $itemsStmt->fetchAll());

    return [
        'id' => (int) $row['id'],
        'orderNumber' => $row['order_number'],
        'customerName' => $row['customer_name'],
        'amount' => (int) $row['amount'],
        'paymentMethod' => $row['payment_method'],
        'paymentStatusLabel' => $row['payment_status_label'],
        'paymentStatusClass' => $row['payment_status_class'],
        'paymentSyncNote' => $row['payment_sync_note'],
        'shippingStatusLabel' => $row['shipping_status_label'],
        'shippingStatusClass' => $row['shipping_status_class'],
        'shippingSyncNote' => $row['shipping_sync_note'],
        'invoiced' => (bool) $row['invoiced'],
        'invoiceFile' => $row['invoice_file'],
        'invoiceStoredName' => $row['invoice_stored_name'],
        'courier' => $row['courier'],
        'orderDate' => $row['order_date'],
        'orderTime' => $row['order_time'],
        'address' => $row['address'],
        'stripeRef' => $row['stripe_ref'],
        'isListed' => (bool) $row['is_listed'],
        'couponCode' => $row['coupon_code'],
        'shipAddress' => $row['ship_address'],
        'shipCity' => $row['ship_city'],
        'shipPostcode' => $row['ship_postcode'],
        'shipCountry' => $row['ship_country'],
        'deliveryPointType' => $row['delivery_point_type'],
        'deliveryPointId' => $row['delivery_point_id'],
        'kvikkShipmentStatus' => $row['kvikk_shipment_status'],
        'kvikkTrackingNumber' => $row['kvikk_tracking_number'],
        'kvikkTrackingLink' => $row['kvikk_tracking_link'],
        'kvikkLabelStoredName' => $row['kvikk_label_stored_name'],
        'kvikkShipmentError' => $row['kvikk_shipment_error'],
        'items' => $items,
    ];
}

if ($method === 'GET') {
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
    } elseif ($number !== null) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
        $stmt->execute([$number]);
    } else {
        $rows = $pdo->query('SELECT * FROM orders ORDER BY order_date DESC, order_time DESC, id DESC')->fetchAll();
        json_response(array_map(fn($r) => order_row_to_json($pdo, $r), $rows));
    }
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen rendelés.'], 404);
    json_response(order_row_to_json($pdo, $row));
}

// A rendelések maguk (állapotok, tételek) a shopból/Kvikk-webhookból jönnének —
// innen csak a számlázási jelölés írható, ez az egyetlen valós admin-oldali módosítás.
if ($method === 'PUT' && ($id !== null || $number !== null)) {
    $input = json_input();
    $sets = [];
    $values = [];
    if (array_key_exists('invoiced', $input)) {
        $sets[] = 'invoiced = ?';
        $values[] = $input['invoiced'] ? 1 : 0;
    }
    if (array_key_exists('invoiceFile', $input)) {
        $sets[] = 'invoice_file = ?';
        $values[] = $input['invoiceFile'];
    }
    if (array_key_exists('invoiceStoredName', $input)) {
        $sets[] = 'invoice_stored_name = ?';
        $values[] = $input['invoiceStoredName'];
    }
    if (empty($sets)) json_response(['error' => 'Nincs frissítendő mező.'], 400);
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';

    if ($id !== null) {
        $values[] = $id;
        $stmt = $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?');
    } else {
        $values[] = $number;
        $stmt = $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE order_number = ?');
    }
    $stmt->execute($values);

    $stmt = $id !== null
        ? $pdo->prepare('SELECT * FROM orders WHERE id = ?')
        : $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
    $stmt->execute([$id !== null ? $id : $number]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen rendelés.'], 404);
    json_response(order_row_to_json($pdo, $row));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
