<?php
/**
 * Small, dependency-free helpers used across the app.
 * Single source of truth for: h(), url(), redirect(), flash, format helpers.
 */

declare(strict_types=1);

/**
 * HTML-escape a value for output. ALWAYS wrap dynamic output in this.
 */
function h($s): string
{
    if ($s === null) return '';
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Cache-busted asset URL.
 *   echo '<link href="' . url('css/admin.css') . '">';
 */
function url(string $path): string
{
    static $cached = [];
    if (!isset($cached[$path])) {
        $abs = __DIR__ . '/../' . ltrim($path, '/');

        // Get the folder name of this project (e.g., college-sports-faculty)
        $dir_name = basename(dirname(__DIR__));

        // Get script name, e.g. /college-sports-faculty/admin/dashboard.php
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $script_name = str_replace('\\', '/', $script_name);

        // Check if the script path starts with the project directory name as a segment
        $prefix = '';
        if (strpos($script_name, '/' . $dir_name . '/') === 0) {
            $prefix = '/' . $dir_name;
        }

        $web_path = $prefix . '/' . ltrim($path, '/');

        // Add cache buster if file exists
        $cached[$path] = $web_path . (is_file($abs) ? '?v=' . filemtime($abs) : '');
    }
    return $cached[$path];
}

/**
 * 302 redirect + exit. Pass an absolute or root-relative path.
 * Always emits a full absolute URL so the browser never mistakes
 * a relative path for a domain name.
 */
function redirect(string $path): never
{
    // Already absolute? Send as-is.
    if (preg_match('~^https?://~i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    // Make it root-relative first
    if ($path[0] !== '/') {
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $path = $dir . '/' . ltrim($path, '/');
    }

    // Resolve any "../" segments
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '..') { array_pop($parts); }
        elseif ($seg !== '' && $seg !== '.') { $parts[] = $seg; }
    }
    $path = '/' . implode('/', $parts);

    // Build full absolute URL
    $forwarded_proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded_proto === 'https')
        ? 'https'
        : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    header('Location: ' . $scheme . '://' . $host . $path);
    exit;
}

/* ---------------- flash messages (PRG pattern) ---------------- */

function flash_set(string $key, string $msg, string $level = 'info', array $meta = []): void
{
    $entry = ['msg' => $msg, 'level' => $level];
    if ($meta) $entry['meta'] = $meta;
    $_SESSION['_flash'][$key] = $entry;
}
function flash_get(string $key): ?array
{
    if (!isset($_SESSION['_flash'][$key])) return null;
    $f = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $f;
}
function flash_pull(string $key): string
{
    $f = flash_get($key);
    return $f ? $f['msg'] : '';
}

/* ---------------- formatters ---------------- */

function format_date($d, string $fmt = 'd M Y'): string
{
    if (empty($d) || $d === '0000-00-00') return '';
    $ts = is_numeric($d) ? (int)$d : strtotime((string)$d);
    return $ts ? date($fmt, $ts) : '';
}

/**
 * Academic sessions roll over on June 1.
 * Example: 2026-05-31 => 2025-26, 2026-06-01 => 2026-27.
 */
function current_academic_year(?DateTimeInterface $date = null): string
{
    $date ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $calendarYear = (int)$date->format('Y');
    $startYear = (int)$date->format('n') >= 6 ? $calendarYear : $calendarYear - 1;

    return $startYear . '-' . substr((string)($startYear + 1), -2);
}

function academic_year_options(): array
{
    $current = current_academic_year();
    $currentStart = (int)substr($current, 0, 4);
    $start = $currentStart - 2;
    $opts  = [];
    for ($y = $start; $y <= $start + 5; $y++) {
        $opts[] = $y . '-' . substr((string)($y + 1), -2);
    }
    return $opts;
}

function sport_options(): array
{
    return [
        'Athletics','Badminton','Basketball','Boxing','Carrom','Chess',
        'Cricket','Football','Handball','Hockey','Judo','Kabaddi',
        'Kho Kho','Lawn Tennis','Shooting','Swimming','Table Tennis',
        'Throwball','Volleyball','Weightlifting','Wrestling','Yoga',
    ];
}

function gender_options(): array  { return ['Male','Female','Other']; }
function blood_options(): array  { return ['A+','A-','B+','B-','O+','O-','AB+','AB-']; }
function year_options(): array   { return ['First','Second','Third','Final']; }

function dept_label(?int $id, ?array $depts = null): string
{
    static $cache = null;
    if ($id === null) return '—';
    if ($cache === null) {
        $cache = [];
        foreach (db_select('SELECT id, name FROM departments') as $r) {
            $cache[(int)$r['id']] = $r['name'];
        }
    }
    return $cache[$id] ?? '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach ($parts as $p) if ($p !== '') $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return mb_substr($out, 0, 2) ?: '?';
}
