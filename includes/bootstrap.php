<?php
/**
 * Single entry point. All .php files that need DB/session/helpers start with:
 *   require __DIR__ . '/../includes/bootstrap.php';
 * (or the appropriate relative path).
 */

declare(strict_types=1);

/* ---------------- configuration & DB ---------------- */
// db.php defines DB_* and APP_ENV constants used by the rest of bootstrap.
require_once __DIR__ . '/db.php';

/* ---------------- site URL ---------------- */
// Trusted public base URL used to build absolute links in emails, password
// resets, and any other outbound URLs. NEVER use $_SERVER['HTTP_HOST'] for
// this — attackers can poison the Host header to redirect victims.
if (!defined('SITE_URL')) {
    // Override via Apache/Nginx env var SITE_URL, or edit this default.
    $env = getenv('SITE_URL');
    define('SITE_URL', $env !== false && $env !== ''
        ? rtrim($env, '/')
        : 'http://localhost/college-sports-faculty');
}

/* ---------------- error reporting ---------------- */

if (APP_ENV === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

/* ---------------- session ---------------- */

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_name('CSF_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_start();
}

/* ---------------- autoload sibling includes ---------------- */
// db.php is already required above; load the rest here.

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';

/* ---------------- security headers ---------------- */

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
         . "img-src 'self' data: https: uploads/; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
         . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
         . "frame-ancestors 'self'");
}
