<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// A sablon neve és a küldés-alkalom (trigger) fix — azt a rendszer definiálja, nem az admin;
// itt csak a tárgy/szöveg/aktív-állapot szerkeszthető, ahogy a felületen is.

function template_row_to_json(array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'triggerLabel' => $row['trigger_label'],
        'subject' => $row['subject'],
        'body' => $row['body'],
        'status' => $row['status'],
        'updatedAt' => $row['updated_at'],
    ];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM email_templates ORDER BY id ASC')->fetchAll();
    json_response(array_map('template_row_to_json', $rows));
}

if ($method === 'PUT' && $id !== null) {
    $input = json_input();
    $sets = [];
    $values = [];
    foreach (['subject' => 'subject', 'body' => 'body', 'status' => 'status'] as $jsKey => $col) {
        if (!array_key_exists($jsKey, $input)) continue;
        $sets[] = "$col = ?";
        $values[] = $input[$jsKey];
    }
    if (empty($sets)) json_response(['error' => 'Nincs frissítendő mező.'], 400);
    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    $values[] = $id;

    $stmt = $pdo->prepare('UPDATE email_templates SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen sablon.'], 404);
    json_response(template_row_to_json($row));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
