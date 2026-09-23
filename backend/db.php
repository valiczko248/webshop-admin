<?php
// Lokális fejlesztés: SQLite fájl, nincs külön szerver-telepítés.
// Éles (Rackhost) környezetben majd MySQL-re áll át egy config.php hozzáadásával.

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

    $isNew = !file_exists($dataDir . '/app.sqlite');

    $pdo = new PDO('sqlite:' . $dataDir . '/app.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

    if ($isNew) {
        require __DIR__ . '/seed.php';
        seed_database($pdo);
    }

    return $pdo;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
