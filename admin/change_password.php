<?php
/**
 * Mandatory password change for newly created or administrator-reset accounts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me = current_faculty();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $current = db_one('SELECT password_hash FROM faculty WHERE id = ?', [$me['id']], 'i');
        if ($current && password_verify($password, $current['password_hash'])) {
            $error = 'Choose a password different from the temporary password.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            db_execute(
                'UPDATE faculty SET password_hash = ?, must_reset_pw = 0 WHERE id = ?',
                [$hash, $me['id']],
                'si'
            );
            $_SESSION['must_reset_pw'] = 0;
            csrf_rotate();
            flash_set('dashboard_info', 'Password updated successfully.', 'success');
            redirect('dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password | Sports Portal</title>
    <link rel="stylesheet" href="<?= h(url('css/public.css')) ?>">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f2744;font-family:Inter,system-ui,sans-serif;color:#212529}
        .card{width:min(420px,calc(100% - 2rem));background:#fff;border-radius:14px;padding:2rem;box-shadow:0 16px 45px rgba(0,0,0,.28)}
        h1{margin:0 0 .4rem;color:#1a365d;font-size:1.45rem}p{color:#6c757d;margin:0 0 1.4rem}
        label{display:block;margin:.9rem 0 .35rem;color:#1a365d;font-size:.82rem;font-weight:700}
        input{width:100%;box-sizing:border-box;padding:.75rem;border:1px solid #dfe3e8;border-radius:8px;font:inherit}
        button{width:100%;margin-top:1.25rem;padding:.8rem;border:0;border-radius:8px;background:#1a365d;color:#fff;font:inherit;font-weight:700;cursor:pointer}
        .error{padding:.75rem;border-radius:8px;background:#f8d7da;color:#842029;margin-bottom:1rem}
        .logout{display:block;text-align:center;margin-top:1rem;color:#6c757d;font-size:.85rem}
    </style>
</head>
<body>
<main class="card">
    <h1>Set a new password</h1>
    <p>Your temporary password must be changed before continuing.</p>
    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="password">New Password</label>
        <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <label for="password_confirm">Confirm Password</label>
        <input id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
        <button type="submit">Update Password</button>
    </form>
    <a class="logout" href="logout.php">Sign out</a>
</main>
</body>
</html>
