<?php
require_once __DIR__ . '/db.php';

// A settings táblában lévő feladó név/cím alapján küld emailt a szerver saját PHP mail()
// funkciójával — ugyanaz a mechanizmus, amit az Emailek oldal is leír (cPanel-tárhelyen
// nincs szükség külön SMTP-fiókra). Helyi fejlesztéskor a mail() valós MTA nélkül nem
// kézbesít semmit, de a hívás hibamentesen lefut — ilyenkor a hívó oldal (pl.
// reset-password link) máshogy is elérhetővé teszi a szükséges adatot teszteléshez.
function send_mail(string $to, string $subject, string $body): bool {
    $pdo = db();
    $rows = $pdo->query("SELECT key, value FROM settings WHERE key IN ('email_sender_name','email_sender_address')")->fetchAll();
    $settings = [];
    foreach ($rows as $row) $settings[$row['key']] = $row['value'];
    $senderName = $settings['email_sender_name'] ?? 'Webshop';
    $senderAddress = $settings['email_sender_address'] ?? 'no-reply@localhost';

    $headers = "From: {$senderName} <{$senderAddress}>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}
