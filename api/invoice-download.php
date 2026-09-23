<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

$pdo = db();
$orderNumber = $_GET['order'] ?? '';
$stmt = $pdo->prepare('SELECT invoice_stored_name, invoice_file FROM orders WHERE order_number = ?');
$stmt->execute([$orderNumber]);
$row = $stmt->fetch();

if (!$row || !$row['invoice_stored_name']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Nincs csatolt számla ehhez a rendeléshez.');
}

$path = __DIR__ . '/../uploads/invoices/' . $row['invoice_stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('A fájl nem található a szerveren.');
}

$displayName = $row['invoice_file'] ?: $row['invoice_stored_name'];
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($displayName) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
