<?php
require __DIR__ . '/../backend/db.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// oszlopnevek <-> a frontend JSON mezőnevei
$fields = [
    'type' => 'type',
    'name' => 'name',
    'price' => 'price',
    'weight' => 'weight',
    'stock' => 'stock',
    'reserved' => 'reserved',
    'deduction' => 'deduction',
    'linkedName' => 'linked_name',
    'linkedQty' => 'linked_qty',
    'linkedWeight' => 'linked_weight',
    'gallery' => 'gallery',
    'variants' => 'variants',
    'addons' => 'addons',
    'archived' => 'archived',
];
$jsonColumns = ['gallery', 'variants', 'addons'];

function row_to_product(array $row): array {
    return [
        'id' => (int) $row['id'],
        'type' => $row['type'],
        'name' => $row['name'],
        'price' => $row['price'] !== null ? (int) $row['price'] : null,
        'weight' => $row['weight'] !== null ? (int) $row['weight'] : null,
        'stock' => (int) $row['stock'],
        'reserved' => (int) $row['reserved'],
        'deduction' => (int) $row['deduction'],
        'linkedName' => $row['linked_name'],
        'linkedQty' => $row['linked_qty'] !== null ? (int) $row['linked_qty'] : null,
        'linkedWeight' => $row['linked_weight'] !== null ? (int) $row['linked_weight'] : null,
        'gallery' => json_decode($row['gallery'], true) ?? [],
        'variants' => json_decode($row['variants'], true) ?? [],
        'addons' => json_decode($row['addons'], true) ?? [],
        'archived' => (bool) $row['archived'],
        'updatedAt' => $row['updated_at'],
    ];
}

if ($method === 'GET') {
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Nincs ilyen termék.'], 404);
        json_response(row_to_product($row));
    }
    $rows = $pdo->query('SELECT * FROM products ORDER BY id ASC')->fetchAll();
    json_response(array_map('row_to_product', $rows));
}

if ($method === 'POST' && $id === null) {
    $input = json_input();
    if (empty($input['name'])) json_response(['error' => 'A termék neve kötelező.'], 400);

    $columns = [];
    $placeholders = [];
    $values = [];
    foreach ($fields as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $value = $input[$jsKey];
        if (in_array($col, $jsonColumns, true)) $value = json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        if ($jsKey === 'archived') $value = $value ? 1 : 0;
        $columns[] = $col;
        $placeholders[] = '?';
        $values[] = $value;
    }
    $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    $newId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$newId]);
    json_response(row_to_product($stmt->fetch()), 201);
}

if ($method === 'PUT' && $id !== null) {
    $input = json_input();
    $sets = [];
    $values = [];
    foreach ($fields as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $value = $input[$jsKey];
        if (in_array($col, $jsonColumns, true)) $value = json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        if ($jsKey === 'archived') $value = $value ? 1 : 0;
        $sets[] = "$col = ?";
        $values[] = $value;
    }
    if (empty($sets)) json_response(['error' => 'Nincs frissítendő mező.'], 400);
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    $values[] = $id;

    $stmt = $pdo->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen termék.'], 404);
    json_response(row_to_product($row));
}

if ($method === 'DELETE' && $id !== null) {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
