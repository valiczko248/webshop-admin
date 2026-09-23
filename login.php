<?php
require_once __DIR__ . '/backend/auth.php';

if (!admin_exists()) {
    header('Location: /setup.php');
    exit;
}
if (is_logged_in()) {
    header('Location: /admin/index.php');
    exit;
}

$error = null;
$ip = client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Lejárt űrlap — próbáld újra.';
    } elseif (is_rate_limited($ip)) {
        $error = 'Túl sok sikertelen próbálkozás. Kérlek, várj ' . LOGIN_WINDOW_MINUTES . ' percet, majd próbáld újra.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            clear_login_attempts($ip);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];
            $pdo->prepare('UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
            if ($rememberMe) set_remember_cookie((int) $user['id']);
            header('Location: /admin/index.php');
            exit;
        }
        record_login_attempt($ip);
        $error = 'Hibás email cím vagy jelszó.';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bejelentkezés</title>
<link rel="stylesheet" href="admin/style.css">
<style>
  .auth-page{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
  .auth-card{width:100%; max-width:360px; padding:28px 26px;}
  .auth-title{font-size:19px; font-weight:700; margin:0 0 22px; display:flex; align-items:center; gap:10px;}
  .auth-title .brand-mark{width:32px; height:32px; border-radius:8px; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; flex-shrink:0;}
  .auth-field{margin-bottom:14px;}
  .auth-error{background:var(--danger-bg); color:var(--danger-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px;}
  .auth-submit{width:100%; margin-top:6px;}
</style>
</head>
<body>
<div class="auth-page">
  <div class="card auth-card">
    <h1 class="auth-title"><span class="brand-mark">W</span> Bejelentkezés</h1>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="field auth-field">
        <label for="email">Email cím</label>
        <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field auth-field">
        <label for="password">Jelszó</label>
        <input type="password" id="password" name="password" required>
      </div>
      <label class="checkbox-item" style="border:none; padding:0 0 14px; width:auto;">
        <input type="checkbox" name="remember_me">
        <span style="font-size:12.5px; color:var(--text-soft); cursor:pointer;">Emlékezz rám ezen a gépen (30 nap)</span>
      </label>
      <button type="submit" class="btn auth-submit">Belépés</button>
    </form>
    <p style="text-align:center; margin:16px 0 0;"><a href="/forgot-password.php" style="font-size:12.5px; color:var(--text-soft);">Elfelejtett jelszó?</a></p>
  </div>
</div>
</body>
</html>
