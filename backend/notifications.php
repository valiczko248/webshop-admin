<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// A rendelés vevőjének email/telefon adata a customers táblából jön, order_number alapján
// (az orders tábla maga nem tárol emailt/telefont — ez már a webshop.php-ban is így lett
// eltervezve, hogy ne duplikálódjon a vevő-adat).
function find_order_contact(PDO $pdo, string $orderNumber): array {
    $stmt = $pdo->prepare('
        SELECT c.email, c.phone FROM customers c
        JOIN customer_purchases cp ON cp.customer_id = c.id
        WHERE cp.order_number = ? LIMIT 1
    ');
    $stmt->execute([$orderNumber]);
    $row = $stmt->fetch();
    return $row ?: ['email' => null, 'phone' => null];
}

// Egy email_templates sablon alapján küld levelet — csak akkor, ha a sablon "Aktív" (status =
// 'success'), így az admin az Emailek oldalon bármikor kikapcsolhatja egy-egy értesítést anélkül,
// hogy a webhook-kódot kellene módosítani. $vars kulcsai a sablonban használt {{...}} placeholderek.
function send_template_email(string $templateName, ?string $toEmail, array $vars): bool {
    if (!$toEmail) return false;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE name = ?');
    $stmt->execute([$templateName]);
    $tpl = $stmt->fetch();
    if (!$tpl || $tpl['status'] !== 'success') return false;

    $subject = strtr($tpl['subject'], $vars);
    $body = strtr($tpl['body'], $vars);
    return send_mail($toEmail, $subject, $body);
}
