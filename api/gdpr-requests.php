<?php
require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/notifications.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

function gdpr_row_to_json(array $row): array {
    return [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'requestType' => $row['request_type'],
        'receivedDate' => $row['received_date'],
        'status' => $row['status'],
    ];
}

if ($method === 'GET') {
    $rows = $pdo->query('SELECT * FROM gdpr_requests ORDER BY received_date DESC, id DESC')->fetchAll();
    json_response(array_map('gdpr_row_to_json', $rows));
}

if ($method === 'POST' && $id === null) {
    $input = json_input();
    if (empty($input['email'])) json_response(['error' => 'A vevő email címe kötelező.'], 400);

    $stmt = $pdo->prepare('
        INSERT INTO gdpr_requests (email, request_type, received_date, status)
        VALUES (:email, :request_type, :received_date, :status)
    ');
    $stmt->execute([
        'email' => $input['email'],
        'request_type' => $input['requestType'] ?? 'Adatexport',
        'received_date' => $input['receivedDate'] ?? date('Y-m-d'),
        'status' => 'warn',
    ]);
    $newId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM gdpr_requests WHERE id = ?');
    $stmt->execute([$newId]);
    json_response(gdpr_row_to_json($stmt->fetch()), 201);
}

if ($method === 'PUT' && $id !== null) {
    $input = json_input();
    if (!array_key_exists('status', $input)) json_response(['error' => 'Nincs frissítendő mező.'], 400);

    $before = $pdo->prepare('SELECT status FROM gdpr_requests WHERE id = ?');
    $before->execute([$id]);
    $previousStatus = $before->fetchColumn();

    $stmt = $pdo->prepare('UPDATE gdpr_requests SET status = ? WHERE id = ?');
    $stmt->execute([$input['status'], $id]);

    $stmt = $pdo->prepare('SELECT * FROM gdpr_requests WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_response(['error' => 'Nincs ilyen kérelem.'], 404);

    // Csak az átmenetkor küldünk emailt (nem minden mentésnél), hogy egy már teljesített
    // kérelem ismételt mentése ne küldjön újra levelet.
    if ($row['status'] === 'success' && $previousStatus !== 'success') {
        send_template_email('GDPR-kérelem teljesítve', $row['email'], []);
    }

    json_response(gdpr_row_to_json($row));
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
