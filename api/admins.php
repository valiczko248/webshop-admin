<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

function admin_row_to_json(array $row): array {
    return [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'createdAt' => $row['created_at'],
        'lastLoginAt' => $row['last_login_at'],
    ];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll();
    json_response(array_map('admin_row_to_json', $rows));
}

if ($method === 'POST' && $id === null) {
    $input = json_input();
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['error' => 'Adj meg egy érvényes email címet.'], 400);
    if (strlen($password) < 8) json_response(['error' => 'A jelszó legyen legalább 8 karakter.'], 400);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int) $stmt->fetchColumn() > 0) json_response(['error' => 'Már van ilyen email című admin.'], 400);

    $pdo->prepare('INSERT INTO admin_users (email, password_hash) VALUES (?, ?)')
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    $newId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$newId]);
    json_response(admin_row_to_json($stmt->fetch()), 201);
}

if ($method === 'DELETE' && $id !== null) {
    if ($id === current_admin_id()) json_response(['error' => 'A saját fiókodat nem törölheted.'], 400);

    $total = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($total <= 1) json_response(['error' => 'Legalább egy admin fióknak léteznie kell.'], 400);

    $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
