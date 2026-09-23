<?php
require_once __DIR__ . '/backend/auth.php';

if (admin_exists()) {
    header('Location: /login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Lejárt űrlap — próbáld újra.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adj meg egy érvényes email címet.';
    } elseif (strlen($password) < 8) {
        $error = 'A jelszó legyen legalább 8 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'A két jelszó nem egyezik.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash, last_login_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['admin_id'] = (int) $pdo->lastInsertId();
        header('Location: /admin/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin fiók létrehozása</title>
<link rel="stylesheet" href="admin/style.css">
<style>
  .auth-page{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
  .auth-card{width:100%; max-width:380px; padding:28px 26px;}
  .auth-title{font-size:19px; font-weight:700; margin:0 0 6px;}
  .auth-sub{font-size:13px; color:var(--text-soft); margin:0 0 22px; line-height:1.5;}
  .auth-field{margin-bottom:14px;}
  .auth-error{background:var(--danger-bg); color:var(--danger-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px;}
  .auth-submit{width:100%; margin-top:6px;}
</style>
</head>
<body>
<div class="auth-page">
  <div class="card auth-card">
    <h1 class="auth-title">Admin fiók létrehozása</h1>
    <p class="auth-sub">Ez csak egyszer, most jelenik meg — itt hozod létre a saját bejelentkezési adataidat ehhez az admin felülethez. Ezután minden alkalommal a bejelentkező oldalon kell megadnod.</p>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="field auth-field">
        <label for="email">Email cím</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field auth-field">
        <label for="password">Jelszó (min. 8 karakter)</label>
        <input type="password" id="password" name="password" required minlength="8">
      </div>
      <div class="field auth-field">
        <label for="password_confirm">Jelszó megerősítése</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn auth-submit">Fiók létrehozása</button>
    </form>
  </div>
</div>
</body>
</html>
