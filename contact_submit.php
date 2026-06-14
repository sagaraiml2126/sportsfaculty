<?php
/**
 * Accept public Sports Department contact messages.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

csrf_check();

// Quietly accept obvious bot submissions without storing them.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    flash_set('contact_result', 'Thank you. Your message has been received.', 'success');
    redirect('index.php#contact-section');
}

$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'Enter a valid name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    $errors[] = 'Enter a valid email address.';
}
if ($phone !== '' && (!preg_match('/^[0-9+().\s-]{7,20}$/', $phone) || mb_strlen($phone) > 20)) {
    $errors[] = 'Enter a valid phone number.';
}
if (mb_strlen($subject) > 160) {
    $errors[] = 'The subject is too long.';
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
    $errors[] = 'The message must be between 10 and 3000 characters.';
}

if ($errors) {
    flash_set('contact_result', implode(' ', $errors), 'error');
    redirect('index.php#contact-section');
}

$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
if ($ip === false) {
    error_log('[contact] rejected request with invalid REMOTE_ADDR');
    flash_set('contact_result', 'Unable to accept the message right now. Please try again later.', 'error');
    redirect('index.php#contact-section');
}

$recent = db_one(
    'SELECT COUNT(*) AS n
       FROM contact_messages
      WHERE ip = INET6_ATON(?)
        AND created_at > (NOW() - INTERVAL 1 HOUR)',
    [$ip],
    's'
);
if ((int)($recent['n'] ?? 0) >= 5) {
    flash_set('contact_result', 'Too many messages were sent from this connection. Please try again later.', 'error');
    redirect('index.php#contact-section');
}

$userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
db_insert(
    'INSERT INTO contact_messages
        (name, email, phone, subject, message, ip, user_agent)
     VALUES (?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, INET6_ATON(?), NULLIF(?, \'\'))',
    [$name, $email, $phone, $subject, $message, $ip, $userAgent],
    'sssssss'
);

flash_set('contact_result', 'Thank you. Your message has been sent to the Sports Department.', 'success');
redirect('index.php#contact-section');
