<?php
require_once __DIR__ . '/backend/auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = $token ? find_password_reset($token) : null;
$error = null;
$done = false;

if (!$reset) {
    $error = 'Ez a link érvénytelen vagy már lejárt. Kérj egy újat.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Lejárt űrlap — próbáld újra.';
    } elseif (strlen($password) < 8) {
        $error = 'A jelszó legyen legalább 8 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'A két jelszó nem egyezik.';
    } else {
        $pdo = db();
        $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['admin_id']]);
        consume_password_reset((int) $reset['id']);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Új jelszó beállítása</title>
<link rel="stylesheet" href="admin/style.css">
<style>
  .auth-page{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
  .auth-card{width:100%; max-width:360px; padding:28px 26px;}
  .auth-title{font-size:19px; font-weight:700; margin:0 0 22px;}
  .auth-field{margin-bottom:14px;}
  .auth-error{background:var(--danger-bg); color:var(--danger-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px;}
  .auth-success{background:var(--success-bg); color:var(--success-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px; line-height:1.5;}
  .auth-submit{width:100%; margin-top:6px;}
</style>
</head>
<body>
<div class="auth-page">
  <div class="card auth-card">
    <h1 class="auth-title">Új jelszó beállítása</h1>
    <?php if ($done): ?>
      <p class="auth-success">A jelszavad megváltozott — most már bejelentkezhetsz az újjal.</p>
      <a href="/login.php" class="btn auth-submit" style="text-align:center;">Tovább a bejelentkezéshez</a>
    <?php elseif (!$reset): ?>
      <div class="auth-error"><?= htmlspecialchars($error) ?></div>
      <a href="/forgot-password.php" class="btn auth-submit" style="text-align:center;">Új link kérése</a>
    <?php else: ?>
      <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field auth-field">
          <label for="password">Új jelszó (min. 8 karakter)</label>
          <input type="password" id="password" name="password" required minlength="8" autofocus>
        </div>
        <div class="field auth-field">
          <label for="password_confirm">Új jelszó megerősítése</label>
          <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
        </div>
        <button type="submit" class="btn auth-submit">Jelszó mentése</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
