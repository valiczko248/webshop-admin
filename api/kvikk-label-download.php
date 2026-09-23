<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$orderNumber = $_GET['order'] ?? '';
$stmt = $pdo->prepare('SELECT kvikk_label_stored_name, kvikk_tracking_number FROM orders WHERE order_number = ?');
$stmt->execute([$orderNumber]);
$row = $stmt->fetch();

if (!$row || !$row['kvikk_label_stored_name']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Nincs Kvikk-címke ehhez a rendeléshez.');
}

$path = __DIR__ . '/../uploads/labels/' . $row['kvikk_label_stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('A fájl nem található a szerveren.');
}

$displayName = 'kvikk-cimke-' . ($row['kvikk_tracking_number'] ?: $orderNumber) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($displayName) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
