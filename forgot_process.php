<?php
/**
 * Forgot-password handler. Always responds with a generic message
 * to avoid leaking which accounts exist. In dev (APP_ENV=local) we
 * also flash the reset link so the user can copy it.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

csrf_check();

$key = trim((string)($_POST['email_or_username'] ?? ''));
if ($key === '') {
    flash_set('forgot_info', 'Please enter your email or username.', 'info');
    redirect('forgot-password.php');
}

$user = db_one(
    'SELECT id, email FROM faculty WHERE (email = ? OR username = ?) AND is_active = 1',
    [$key, $key], 'ss'
);

$generic = 'If an account exists for the given email or username, a reset link has been sent. Check your inbox.';

if (!$user) {
    // Don't reveal that the user doesn't exist
    flash_set('forgot_info', $generic, 'info');
    redirect('forgot-password.php');
}

$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$recent = db_one(
    'SELECT COUNT(*) AS n FROM password_resets
      WHERE ip = INET6_ATON(?) AND created_at > (NOW() - INTERVAL 15 MINUTE)',
    [$ip],
    's'
);
if ((int)($recent['n'] ?? 0) >= 5) {
    flash_set('forgot_info', $generic, 'info');
    redirect('forgot-password.php');
}

db_execute(
    'DELETE FROM password_resets
      WHERE used_at IS NOT NULL OR expires_at < (NOW() - INTERVAL 1 DAY)'
);

// Generate token, store hash
$token     = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
$expires   = date('Y-m-d H:i:s', time() + 1800); // 30 min

db_insert(
    'INSERT INTO password_resets (faculty_id, token_hash, expires_at, ip)
     VALUES (?,?,?,INET6_ATON(?))',
    [$user['id'], $token_hash, $expires, $ip],
    'isss'
);

$reset_url = SITE_URL . '/reset_password.php?token=' . $token;

$flash = ['msg' => $generic, 'level' => 'info'];
if (APP_ENV === 'local') {
    $flash['dev_link'] = $reset_url;
} else {
    $subject = 'Reset your Sports Portal password';
    $body = "A password reset was requested for your account.\n\n"
          . "Open this link within 30 minutes:\n$reset_url\n\n"
          . "If you did not request this, you can ignore this message.";
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Sports Portal <no-reply@yes.edu.in>',
    ];
    if (!@mail((string)$user['email'], $subject, $body, implode("\r\n", $headers))) {
        error_log('[mail] Password reset email could not be sent.');
    }
}
flash_set('forgot_info', $flash['msg'], 'info');
if (isset($flash['dev_link'])) {
    // Dev link piggybacks on the forgot_info flash itself rather than living
    // in $_SESSION — a stale session can't leak someone else's reset URL.
    $existing = flash_get('forgot_info');
    if (is_array($existing)) {
        flash_set('forgot_info', $existing['msg'], $existing['level'] ?? 'info', ['dev_link' => $flash['dev_link']]);
    }
}
redirect('forgot-password.php');
