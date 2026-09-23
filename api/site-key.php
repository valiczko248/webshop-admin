<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// A webshop → backend webhook (új rendelés) ezt a kulcsot várja az X-API-Key fejlécben —
// lásd api/webhooks/webshop.php. Csak itt generálható újra, mert valódi belépési titok,
// nem sima beállítás (ezért nincs az általános settings.php-n keresztül írva).

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'site_api_key'");
    $stmt->execute();
    json_response(['siteApiKey' => $stmt->fetchColumn() ?: null]);
}

if ($method === 'POST') {
    $newKey = 'whs_live_' . bin2hex(random_bytes(12));
    $pdo->prepare("
        INSERT INTO settings (key, value) VALUES ('site_api_key', ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
    ")->execute([$newKey]);
    json_response(['siteApiKey' => $newKey]);
}

json_response(['error' => 'Ismeretlen kérés.'], 400);
