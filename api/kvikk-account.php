<?php
require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/kvikk_client.php';
require_api_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error' => 'Ismeretlen kérés.'], 400);

$res = kvikk_get_account();
if (!$res['ok']) json_response(['error' => $res['error'], 'kvikkStatus' => $res['httpStatus'] ?? null], 502);

json_response(['ok' => true, 'account' => $res['data']['data'] ?? $res['data']]);
