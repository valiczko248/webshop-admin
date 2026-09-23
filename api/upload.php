<?php
require __DIR__ . '/../backend/auth.php';
require_api_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Ismeretlen kérés.'], 400);

// type=product: termékgaléria kép (nyilvánosan elérhető URL-t ad vissza, ahogy a shopban is látszana).
// type=invoice: számla PDF (nem publikus — csak a saját letöltő végponton, bejelentkezve érhető el).
$allowed = [
    'product' => ['dir' => 'products', 'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 'maxSize' => 5 * 1024 * 1024],
    'invoice' => ['dir' => 'invoices', 'mimes' => ['application/pdf' => 'pdf'], 'maxSize' => 10 * 1024 * 1024],
];

$type = $_POST['type'] ?? '';
if (!isset($allowed[$type])) json_response(['error' => 'Ismeretlen feltöltési típus.'], 400);
$config = $allowed[$type];

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_response(['error' => 'Nem sikerült a fájl feltöltése.'], 400);
}
$file = $_FILES['file'];

if ($file['size'] > $config['maxSize']) {
    json_response(['error' => 'A fájl túl nagy (max ' . round($config['maxSize'] / 1024 / 1024) . ' MB).'], 400);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!isset($config['mimes'][$mime])) {
    json_response(['error' => 'Nem támogatott fájltípus (' . $mime . ').'], 400);
}
$ext = $config['mimes'][$mime];

// Véletlen, biztonságos fájlnév — sosem az eredeti (elkerüli az útvonal-injektálást és a felülírást).
$storedName = bin2hex(random_bytes(16)) . '.' . $ext;
$destDir = __DIR__ . '/../uploads/' . $config['dir'];
if (!is_dir($destDir)) mkdir($destDir, 0777, true);

if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $storedName)) {
    json_response(['error' => 'Nem sikerült elmenteni a fájlt a szerveren.'], 500);
}

$response = ['storedName' => $storedName, 'originalName' => $file['name']];
if ($type === 'product') $response['url'] = '/uploads/products/' . $storedName;

json_response($response, 201);
