<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'samesite' => 'Lax',
        'httponly' => true,
        'secure' => $isHttps,
    ]);
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function current_admin_id(): ?int {
    return $_SESSION['admin_id'] ?? null;
}

function admin_exists(): bool {
    $pdo = db();
    return (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
}

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---- bejelentkezési próbálkozások korlátozása (IP-nként) ----
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
function is_rate_limited(string $ip): bool {
    $stmt = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > datetime('now', ?)");
    $stmt->execute([$ip, '-' . LOGIN_WINDOW_MINUTES . ' minutes']);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}
function record_login_attempt(string $ip): void {
    db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
}
function clear_login_attempts(string $ip): void {
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

// ---- "emlékezz rám" — selector/validator mintás perzisztens bejelentkezés ----
const REMEMBER_COOKIE = 'webshop_remember';
const REMEMBER_DAYS = 30;

function remember_cookie_options(int $expiresAt): array {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ['expires' => $expiresAt, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => $isHttps];
}

function set_remember_cookie(int $adminId): void {
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(33));
    $expiresTs = time() + REMEMBER_DAYS * 86400;

    db()->prepare('INSERT INTO remember_tokens (admin_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$adminId, $selector, hash('sha256', $validator), gmdate('Y-m-d H:i:s', $expiresTs)]);

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, remember_cookie_options($expiresTs));
}

function clear_remember_cookie(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $selector = explode(':', $_COOKIE[REMEMBER_COOKIE], 2)[0] ?? '';
        if ($selector) db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
    }
    setcookie(REMEMBER_COOKIE, '', remember_cookie_options(time() - 3600));
}

// Ha nincs aktív session, de van érvényes "emlékezz rám" süti, csendben visszajelentkeztet
// (a validátort minden felhasználáskor lecseréli — ha egy régi, már felhasznált selector/
// validator párral próbálkoznak, az lopott sütire utal, ezért akkor minden tokent törlünk).
function try_remember_login(): bool {
    if (empty($_COOKIE[REMEMBER_COOKIE])) return false;
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) return false;
    [$selector, $validator] = $parts;

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > datetime('now')");
    $stmt->execute([$selector]);
    $row = $stmt->fetch();
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return false;
    }

    $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $row['admin_id'];
    set_remember_cookie((int) $row['admin_id']);
    return true;
}

// ---- jelszó-visszaállítás ----
const RESET_TOKEN_HOURS = 1;

function create_password_reset(int $adminId): string {
    $token = bin2hex(random_bytes(32));
    $expiresAt = gmdate('Y-m-d H:i:s', time() + RESET_TOKEN_HOURS * 3600);
    db()->prepare('INSERT INTO password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, ?)')
        ->execute([$adminId, hash('sha256', $token), $expiresAt]);
    return $token;
}

function find_password_reset(string $token): ?array {
    $stmt = db()->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > datetime('now')");
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function consume_password_reset(int $resetId): void {
    db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$resetId]);
}

// ---- oldal- és API-védelem ----
function require_login(): void {
    if (!admin_exists()) {
        header('Location: /setup.php');
        exit;
    }
    if (!is_logged_in() && !try_remember_login()) {
        header('Location: /login.php');
        exit;
    }
}

function require_api_auth(): void {
    if (!is_logged_in() && !try_remember_login()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Nincs bejelentkezve.']);
        exit;
    }
}
