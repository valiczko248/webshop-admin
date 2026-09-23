<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// Csak olvasás — a Kvikk-visszaküldés és a Stripe-visszatérítés állapotát is a webhookok
// írnák, itt nincs admin-oldali szerkesztés. A vevő/összeg/Stripe-ref nincs duplikálva:
// az orders táblából jön order_number alapján, hogy ne tudjon szétdriftelni a két hely.
if ($method === 'GET') {
    $rows = $pdo->query('
        SELECT r.order_number, r.return_date, r.kvikk_status, r.stripe_status,
               o.customer_name, o.amount, o.stripe_ref
        FROM returns r
        JOIN orders o ON o.order_number = r.order_number
        ORDER BY r.return_date DESC, r.id DESC
    ')->fetchAll();

    json_response(array_map(function ($row) {
        return [
            'orderNumber' => $row['order_number'],
            'returnDate' => $row['return_date'],
            'kvikkStatus' => $row['kvikk_status'],
            'stripeStatus' => $row['stripe_status'],
            'customerName' => $row['customer_name'],
            'amount' => (int) $row['amount'],
            'stripeRef' => $row['stripe_ref'],
        ];
    }, $rows));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
