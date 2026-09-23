<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$key = isset($_GET['key']) ? $_GET['key'] : null;

// Vásárlók + rendelési előzményük — kizárólag a rendelésekből/shopból származó, olvasható
// adat, itt nincs admin-oldali szerkesztés (nincs is hozzá gomb a felületen).

function customer_row_to_json(PDO $pdo, array $row): array {
    $stmt = $pdo->prepare('SELECT * FROM customer_purchases WHERE customer_id = ? ORDER BY purchase_date DESC, id DESC');
    $stmt->execute([$row['id']]);
    $purchases = array_map(function ($p) {
        return [
            'date' => $p['purchase_date'],
            'order' => $p['order_number'],
            'product' => $p['product_label'],
            'amount' => $p['amount'],
            'status' => $p['status'],
            'stripeRef' => $p['stripe_ref'],
        ];
    }, $stmt->fetchAll());

    $lastPurchase = $purchases[0]['date'] ?? null;

    return [
        'id' => (int) $row['id'],
        'key' => $row['customer_key'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'orderCount' => count($purchases),
        'lastPurchase' => $lastPurchase,
        'purchases' => $purchases,
    ];
}

if ($method === 'GET') {
    if ($key !== null) {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Nincs ilyen vásárló.'], 404);
        json_response(customer_row_to_json($pdo, $row));
    }
    $rows = $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
    json_response(array_map(fn($r) => customer_row_to_json($pdo, $r), $rows));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
