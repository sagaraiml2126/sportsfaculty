<?php
/**
 * Jersey Kit — Export approved orders as CSV.
 *
 * GET params: game, event, ay
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_department();

$me    = current_faculty();
$game  = trim((string)($_GET['game'] ?? ''));
$event = trim((string)($_GET['event'] ?? ''));
$ay    = trim((string)($_GET['ay'] ?? ''));
$deptId = (int)($_GET['dept'] ?? (effective_department_id() ?? 0));

if ($game === '' || $event === '' || $deptId <= 0) {
    flash_set('jersey_error', 'Game and event are required.', 'error');
    redirect('jersey_manage.php');
}
assert_department_access($deptId);

$ay_val = $ay;

// Load the form
$form = db_one(
    "SELECT id FROM jersey_forms
      WHERE department_id = ? AND game_name = ? AND event_label = ? AND academic_year = ?",
    [$deptId, $game, $event, $ay_val], 'isss'
);

if (!$form) {
    flash_set('jersey_error', 'No jersey form found for this team.', 'error');
    redirect('final_list.php');
}

// Load approved requests with student info
$rows = db_select(
    "SELECT jr.enrollment_no, s.full_name, jr.mobile, jr.tshirt_size,
            jr.jersey_name,
            COALESCE(jr.final_number, jr.preferred_number) AS jersey_number,
            jr.status, jr.locked
       FROM jersey_requests jr
       JOIN students s ON s.id = jr.student_id
      WHERE jr.jersey_form_id = ?
        AND s.department_id = ?
        AND jr.status = 'Approved'
      ORDER BY COALESCE(jr.final_number, jr.preferred_number) ASC",
    [(int)$form['id'], $deptId], 'ii'
);

if (empty($rows)) {
    flash_set('jersey_error', 'No approved jersey requests to export.', 'error');
    redirect('jersey_manage.php?' . http_build_query(array_filter([
        'game' => $game, 'event' => $event, 'ay' => $ay, 'dept' => $deptId,
    ])));
}

// Generate CSV
$filename = 'Jersey_Order_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $game)
          . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $event)
          . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$fp = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fwrite($fp, "\xEF\xBB\xBF");

// Header row
fputcsv($fp, ['#', 'Student Name', 'Enrollment No.', 'Mobile', 'T-Shirt Size', 'Jersey Name', 'Jersey Number', 'Status', 'Locked']);

$i = 1;
foreach ($rows as $r) {
    fputcsv($fp, [
        $i++,
        $r['full_name'],
        $r['enrollment_no'],
        $r['mobile'],
        $r['tshirt_size'],
        $r['jersey_name'],
        $r['jersey_number'],
        $r['status'],
        (int)$r['locked'] ? 'Yes' : 'No',
    ]);
}

fclose($fp);
exit;
