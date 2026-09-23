<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

function legal_doc_row_to_json(array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'url' => $row['url'],
        'effectiveDate' => $row['effective_date'],
        'status' => $row['status'],
        'content' => $row['content'],
        'archived' => (bool) $row['archived'],
        'updatedAt' => $row['updated_at'],
    ];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM legal_docs ORDER BY id ASC')->fetchAll();
    json_response(array_map('legal_doc_row_to_json', $rows));
}

if ($method === 'POST' && $id === null) {
    $input = json_input();
    if (empty($input['name'])) json_response(['error' => 'A dokumentum neve kötelező.'], 400);

    $stmt = $pdo->prepare('
        INSERT INTO legal_docs (name, url, effective_date, status, content, archived)
        VALUES (:name, :url, :effective_date, :status, :content, 0)
    ');
    $stmt->execute([
        'name' => $input['name'],
        'url' => $input['url'] ?? null,
        'effective_date' => $input['effectiveDate'] ?? null,
        'status' => $input['status'] ?? 'neutral',
        'content' => $input['content'] ?? null,
    ]);
    $newId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM legal_docs WHERE id = ?');
    $stmt->execute([$newId]);
    json_response(legal_doc_row_to_json($stmt->fetch()), 201);
}

if ($method === 'PUT' && $id !== null) {
    $input = json_input();
    $fields = ['url' => 'url', 'effectiveDate' => 'effective_date', 'status' => 'status', 'content' => 'content', 'archived' => 'archived'];
    $sets = [];
    $values = [];
    foreach ($fields as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $value = $input[$jsKey];
        if ($jsKey === 'archived') $value = $value ? 1 : 0;
        $sets[] = "$col = ?";
        $values[] = $value;
    }
    if (empty($sets)) json_response(['error' => 'Nincs frissítendő mező.'], 400);
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    $values[] = $id;

    $stmt = $pdo->prepare('UPDATE legal_docs SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM legal_docs WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen dokumentum.'], 404);
    json_response(legal_doc_row_to_json($row));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
