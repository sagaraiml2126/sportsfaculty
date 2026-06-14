<?php
/**
 * Authentication, authorization, and department-scoping.
 * The single source of truth for security in the app.
 *
 * Session keys:
 *   faculty_id, faculty_username, faculty_name, faculty_role,
 *   must_reset_pw, department_id, department_code, department_name,
 *   login_time, last_activity
 */

declare(strict_types=1);

const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes
const LOGIN_LOCKOUT_MAX     = 5;
const LOGIN_LOCKOUT_WINDOW  = 900; // 15 minutes

/* ---------------- session shape ---------------- */

function auth_session_keys(): array
{
    return [
        'faculty_id', 'faculty_username', 'faculty_name', 'faculty_role',
        'must_reset_pw', 'department_id', 'department_code', 'department_name',
        'login_time', 'last_activity',
    ];
}

function auth_clear_session(): void
{
    foreach (auth_session_keys() as $k) unset($_SESSION[$k]);
}

function auth_alive(): bool
{
    if (empty($_SESSION['faculty_id'])) return false;
    $last = $_SESSION['last_activity'] ?? 0;
    if (time() - (int)$last > SESSION_IDLE_TIMEOUT) {
        auth_clear_session();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/* ---------------- current user ---------------- */

function current_faculty(): ?array
{
    if (!auth_alive()) return null;
    return [
        'id'            => (int)$_SESSION['faculty_id'],
        'username'      => $_SESSION['faculty_username'],
        'full_name'     => $_SESSION['faculty_name'],
        'role'          => $_SESSION['faculty_role'],
        'must_reset_pw' => (int)($_SESSION['must_reset_pw'] ?? 0),
        'department_id' => isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null,
        'department_code' => $_SESSION['department_code'] ?? null,
        'department_name' => $_SESSION['department_name'] ?? null,
    ];
}

function is_super_admin(): bool
{
    $f = current_faculty();
    return $f && $f['role'] === 'SUPER_ADMIN';
}

/* ---------------- guards ---------------- */

function require_login(): void
{
    $f = current_faculty();
    if (!$f) {
        flash_set('login_error', 'Please sign in to continue.', 'error');
        $prefix = is_file('faculty-login.php') ? '' : '../';
        redirect($prefix . 'faculty-login.php');
    }
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ((int)$f['must_reset_pw'] === 1
        && !in_array($script, ['change_password.php', 'logout.php'], true)) {
        $inAdmin = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin';
        redirect($inAdmin ? 'change_password.php' : 'admin/change_password.php');
    }
}

function require_role(string $role): void
{
    require_login();
    $f = current_faculty();
    if (!$f || $f['role'] !== $role) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

function require_department(): void
{
    require_login();
    $f = current_faculty();
    if ($f['role'] === 'FACULTY') {
        $departmentId = (int)($f['department_id'] ?? 0);
        $isAssigned = $departmentId > 0 && db_one(
            'SELECT 1 FROM faculty_departments WHERE faculty_id = ? AND department_id = ?',
            [$f['id'], $departmentId],
            'ii'
        );
        if (!$isAssigned) {
            unset($_SESSION['department_id'], $_SESSION['department_code'], $_SESSION['department_name']);
            $prefix = is_file('faculty-select.php') ? '' : '../';
            redirect($prefix . 'faculty-select.php');
        }
    }
}

/* ---------------- login / logout ---------------- */

function login_user(array $faculty, ?int $department_id = null): void
{
    session_regenerate_id(true);
    $_SESSION['faculty_id']     = (int)$faculty['id'];
    $_SESSION['faculty_username']= $faculty['username'];
    $_SESSION['faculty_name']    = $faculty['full_name'];
    $_SESSION['faculty_role']    = $faculty['role'];
    $_SESSION['must_reset_pw']   = (int)$faculty['must_reset_pw'];
    $_SESSION['login_time']      = time();
    $_SESSION['last_activity']   = time();
    csrf_rotate();

    if ($faculty['role'] === 'FACULTY' && $department_id) {
        auth_set_department($department_id);
    }
}

function auth_set_department(int $department_id): void
{
    $dept = db_one('SELECT id, code, name FROM departments WHERE id = ? AND is_active = 1',
        [$department_id], 'i');
    if (!$dept) {
        throw new RuntimeException('Invalid department.');
    }
    $_SESSION['department_id']   = (int)$dept['id'];
    $_SESSION['department_code'] = $dept['code'];
    $_SESSION['department_name'] = $dept['name'];
}

function logout_user(): void
{
    auth_clear_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------- login attempts / lockout ---------------- */

function record_login_attempt(string $username, bool $success): void
{
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    db_insert(
        'INSERT INTO login_attempts (username, ip, user_agent, success)
         VALUES (?, INET6_ATON(?), ?, ?)',
        [$username, $ip, $ua, $success ? 1 : 0],
        'sssi'
    );
}

function is_locked_out(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    // If we can't parse the IP, treat the request as untrusted and lock it out
    // (fail-closed) rather than silently disabling brute-force protection.
    $ip = filter_var($remote, FILTER_VALIDATE_IP);
    if ($ip === false) {
        return true;
    }
    $row = db_one(
        'SELECT COUNT(*) AS n FROM login_attempts
         WHERE ip = INET6_ATON(?) AND success = 0
           AND attempted_at > (NOW() - INTERVAL ? SECOND)',
        [$ip, LOGIN_LOCKOUT_WINDOW],
        'si'
    );
    return ((int)($row['n'] ?? 0)) >= LOGIN_LOCKOUT_MAX;
}

/* ---------------- department scoping (the security core) ---------------- */

/**
 * For SUPER_ADMIN, returns null (no scope) or the override from ?dept=N.
 * For FACULTY, returns the session's department_id, ignoring any URL param.
 */
function effective_department_id(): ?int
{
    $f = current_faculty();
    if (!$f) return null;
    if ($f['role'] === 'FACULTY') {
        return $f['department_id'] ?: null;
    }
    // SUPER_ADMIN
    if (isset($_GET['dept']) && ctype_digit((string)$_GET['dept'])) {
        $dept_id = (int)$_GET['dept'];
        // Validate the department actually exists; otherwise an unverified int
        // can be passed through, leading to silent zero-row queries.
        $exists = db_one(
            'SELECT id FROM departments WHERE id = ? AND is_active = 1',
            [$dept_id], 'i'
        );
        if ($exists) {
            return $dept_id;
        }
    }
    return null;
}

/**
 * SQL fragment + bound param that restricts a query to the effective department.
 * Returns [string $fragment, array $params, string $types].
 *
 * Use:
 *   [$where, $p, $t] = scope_sql_department('s');
 *   $sql = "SELECT * FROM students s WHERE 1=1 $where ORDER BY s.full_name";
 *   $rows = db_select($sql, $p, $t);
 */
function scope_sql_department(string $alias = 's'): array
{
    $dept = effective_department_id();
    if ($dept === null) {
        return ['', [], ''];
    }
    return [" AND {$alias}.department_id = ? ", [$dept], 'i'];
}

/**
 * Verify a POSTed department_id matches the effective scope.
 * Reject with 403 if not. Used by student_save.php.
 */
function assert_department_in_scope(?int $posted_dept_id): void
{
    $f = current_faculty();
    if (!$f || $f['role'] !== 'FACULTY') return;
    if ($posted_dept_id !== (int)$f['department_id']) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

/**
 * Verify that the current account may operate on a specific department.
 * Faculty must also have that department selected in the current session.
 */
function assert_department_access(int $department_id): void
{
    $f = current_faculty();
    if (!$f || $department_id <= 0) {
        http_response_code(403);
        exit('Forbidden.');
    }
    if ($f['role'] === 'SUPER_ADMIN') {
        return;
    }
    if ((int)($f['department_id'] ?? 0) !== $department_id) {
        http_response_code(403);
        exit('Forbidden.');
    }
    $assigned = db_one(
        'SELECT 1 FROM faculty_departments WHERE faculty_id = ? AND department_id = ?',
        [$f['id'], $department_id],
        'ii'
    );
    if (!$assigned) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

/**
 * Check if the current logged-in faculty user has multiple assigned departments.
 */
function has_multiple_departments(): bool
{
    $f = current_faculty();
    if (!$f || $f['role'] !== 'FACULTY') {
        return false;
    }
    $count = db_one(
        'SELECT COUNT(*) AS n FROM faculty_departments WHERE faculty_id = ?',
        [$f['id']], 'i'
    );
    return ((int)($count['n'] ?? 0)) > 1;
}
