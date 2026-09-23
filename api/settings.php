<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// Egyszerű kulcs-érték beállítás-tár (pl. email feladó név/cím).

if ($method === 'GET') {
    $rows = $pdo->query('SELECT key, value FROM settings')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['key']] = $row['value'];
    }
    json_response($result);
}

if ($method === 'PUT') {
    $input = json_input();
    $stmt = $pdo->prepare('
        INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
    ');
    foreach ($input as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
    $rows = $pdo->query('SELECT key, value FROM settings')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['key']] = $row['value'];
    }
    json_response($result);
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
