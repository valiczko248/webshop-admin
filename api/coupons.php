<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

$fields = [
    'code' => 'code',
    'discountType' => 'discount_type',
    'discountValue' => 'discount_value',
    'startNow' => 'start_now',
    'from' => 'from_date',
    'until' => 'until_date',
    'limit' => 'usage_limit',
    'usedCount' => 'used_count',
    'limitPerEmail' => 'limit_per_email',
    'status' => 'status',
    'note' => 'note',
    'excludedProducts' => 'excluded_products',
];
$jsonColumns = ['excludedProducts'];
$boolColumns = ['startNow'];

function coupon_row_to_json(array $row): array {
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'discountType' => $row['discount_type'],
        'discountValue' => (int) $row['discount_value'],
        'startNow' => (bool) $row['start_now'],
        'from' => $row['from_date'],
        'until' => $row['until_date'],
        'limit' => $row['usage_limit'] !== null ? (int) $row['usage_limit'] : null,
        'usedCount' => (int) $row['used_count'],
        'limitPerEmail' => $row['limit_per_email'] !== null ? (int) $row['limit_per_email'] : null,
        'status' => $row['status'],
        'note' => $row['note'],
        'excludedProducts' => json_decode($row['excluded_products'], true) ?? [],
        'updatedAt' => $row['updated_at'],
    ];
}

if ($method === 'GET') {
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Nincs ilyen kupon.'], 404);
        json_response(coupon_row_to_json($row));
    }
    $rows = $pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();
    json_response(array_map('coupon_row_to_json', $rows));
}

if ($method === 'POST' && $id === null) {
    $input = json_input();
    if (empty($input['code'])) json_response(['error' => 'A kupon kódja kötelező.'], 400);

    $columns = [];
    $placeholders = [];
    $values = [];
    foreach ($fields as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $value = $input[$jsKey];
        if (in_array($jsKey, $jsonColumns, true)) $value = json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        if (in_array($jsKey, $boolColumns, true)) $value = $value ? 1 : 0;
        $columns[] = $col;
        $placeholders[] = '?';
        $values[] = $value;
    }
    $sql = 'INSERT INTO coupons (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $newId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$newId]);
    json_response(coupon_row_to_json($stmt->fetch()), 201);
}

if ($method === 'PUT' && $id !== null) {
    $input = json_input();
    $sets = [];
    $values = [];
    foreach ($fields as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $value = $input[$jsKey];
        if (in_array($jsKey, $jsonColumns, true)) $value = json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        if (in_array($jsKey, $boolColumns, true)) $value = $value ? 1 : 0;
        $sets[] = "$col = ?";
        $values[] = $value;
    }
    if (empty($sets)) json_response(['error' => 'Nincs frissítendő mező.'], 400);
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    $values[] = $id;

    $stmt = $pdo->prepare('UPDATE coupons SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen kupon.'], 404);
    json_response(coupon_row_to_json($row));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
