<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/mailer.php';

if (!admin_exists()) {
    header('Location: /setup.php');
    exit;
}

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Lejárt űrlap — próbáld újra.';
    } else {
        // Szándékosan ugyanazt az üzenetet mutatjuk attól függetlenül, hogy létezik-e ilyen
        // fiók — így kívülről nem deríthető ki, mely email címekhez tartozik admin-fiók.
        $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = ?');
        $stmt->execute([$email]);
        $adminId = $stmt->fetchColumn();
        if ($adminId) {
            $token = create_password_reset((int) $adminId);
            $resetUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . $token;
            send_mail($email, 'Jelszó-visszaállítás', "Szia!\n\nA következő linken 1 órán belül új jelszót állíthatsz be:\n\n{$resetUrl}\n\nHa nem te kérted, hagyd figyelmen kívül ezt az emailt.");
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Elfelejtett jelszó</title>
<link rel="stylesheet" href="admin/style.css">
<style>
  .auth-page{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;}
  .auth-card{width:100%; max-width:360px; padding:28px 26px;}
  .auth-title{font-size:19px; font-weight:700; margin:0 0 6px;}
  .auth-sub{font-size:13px; color:var(--text-soft); margin:0 0 22px; line-height:1.5;}
  .auth-field{margin-bottom:14px;}
  .auth-error{background:var(--danger-bg); color:var(--danger-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px;}
  .auth-success{background:var(--success-bg); color:var(--success-text); border-radius:6px; padding:10px 12px; font-size:13px; margin-bottom:16px; line-height:1.5;}
  .auth-submit{width:100%; margin-top:6px;}
</style>
</head>
<body>
<div class="auth-page">
  <div class="card auth-card">
    <h1 class="auth-title">Elfelejtett jelszó</h1>
    <?php if ($sent): ?>
      <p class="auth-success">Ha ehhez az email címhez tartozik admin-fiók, elküldtük rá a jelszó-visszaállító linket — nézd meg a postaládád (1 órán belül érvényes).</p>
      <p style="text-align:center; margin:16px 0 0;"><a href="/login.php" style="font-size:12.5px; color:var(--text-soft);">Vissza a bejelentkezéshez</a></p>
    <?php else: ?>
      <p class="auth-sub">Add meg az admin fiókodhoz tartozó email címet — küldünk rá egy linket, amivel új jelszót állíthatsz be.</p>
      <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="field auth-field">
          <label for="email">Email cím</label>
          <input type="email" id="email" name="email" required autofocus>
        </div>
        <button type="submit" class="btn auth-submit">Küldés</button>
      </form>
      <p style="text-align:center; margin:16px 0 0;"><a href="/login.php" style="font-size:12.5px; color:var(--text-soft);">Vissza a bejelentkezéshez</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
