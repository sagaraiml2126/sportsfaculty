<?php
/**
 * Jersey Kit POST actions. Every operation is scoped to one department.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_login();
require_department();
csrf_check();

$action = trim((string)($_POST['action'] ?? ''));
$game   = trim((string)($_POST['game'] ?? ''));
$event  = trim((string)($_POST['event'] ?? ''));
$ay     = trim((string)($_POST['ay'] ?? ''));
$deptId = (int)($_POST['dept'] ?? (effective_department_id() ?? 0));

function jersey_back_url(string $game, string $event, string $ay, int $deptId): string
{
    return 'jersey_manage.php?' . http_build_query([
        'game' => $game,
        'event' => $event,
        'ay' => $ay,
        'dept' => $deptId,
    ]);
}

function jersey_require_team(string $game, string $event, string $ay, int $deptId): void
{
    assert_department_access($deptId);
    $team = db_one(
        'SELECT ft.id
           FROM final_teams ft
           JOIN students s ON s.id = ft.student_id
          WHERE ft.game_name = ? AND ft.event_label = ?
            AND COALESCE(ft.academic_year, \'\') = ?
            AND s.department_id = ?',
        [$game, $event, $ay, $deptId],
        'sssi'
    );
    if (!$team) {
        http_response_code(404);
        exit('Final team not found.');
    }
}

function jersey_scoped_request(int $requestId): ?array
{
    $request = db_one(
        'SELECT jr.*, jf.department_id, jf.game_name, jf.event_label, jf.academic_year
           FROM jersey_requests jr
           JOIN jersey_forms jf ON jf.id = jr.jersey_form_id
          WHERE jr.id = ?',
        [$requestId],
        'i'
    );
    if ($request) {
        assert_department_access((int)$request['department_id']);
    }
    return $request;
}

$back = jersey_back_url($game, $event, $ay, $deptId);

switch ($action) {
    case 'toggle_form':
        if ($game === '' || $event === '' || $deptId <= 0) {
            flash_set('jersey_error', 'A valid team is required.', 'error');
            redirect('jersey_dashboard.php');
        }
        jersey_require_team($game, $event, $ay, $deptId);

        $row = db_one(
            'SELECT id, is_open FROM jersey_forms
              WHERE department_id = ? AND game_name = ? AND event_label = ? AND academic_year = ?',
            [$deptId, $game, $event, $ay],
            'isss'
        );
        if ($row) {
            $newState = (int)$row['is_open'] ? 0 : 1;
            db_execute(
                'UPDATE jersey_forms SET is_open = ? WHERE id = ? AND department_id = ?',
                [$newState, (int)$row['id'], $deptId],
                'iii'
            );
            flash_set(
                'jersey_ok',
                $newState
                    ? 'Jersey form is now open. Students can submit requests.'
                    : 'Jersey form is now closed.',
                'success'
            );
        } else {
            $me = current_faculty();
            try {
                db_insert(
                    'INSERT INTO jersey_forms
                        (department_id, game_name, event_label, academic_year, is_open, access_token, created_by)
                     VALUES (?, ?, ?, ?, 1, ?, ?)',
                    [$deptId, $game, $event, $ay, bin2hex(random_bytes(24)), (int)$me['id']],
                    'issssi'
                );
                flash_set('jersey_ok', 'Jersey form created and opened.', 'success');
            } catch (Throwable $e) {
                // A concurrent request may have created the row first.
                $existing = db_one(
                    'SELECT id FROM jersey_forms
                      WHERE department_id = ? AND game_name = ? AND event_label = ? AND academic_year = ?',
                    [$deptId, $game, $event, $ay],
                    'isss'
                );
                if (!$existing) {
                    throw $e;
                }
                db_execute('UPDATE jersey_forms SET is_open = 1 WHERE id = ?', [(int)$existing['id']], 'i');
                flash_set('jersey_ok', 'Jersey form is now open.', 'success');
            }
        }
        redirect($back);

    case 'approve':
    case 'reject':
    case 'edit_number':
    case 'lock':
        $requestId = (int)($_POST['request_id'] ?? 0);
        $request = $requestId > 0 ? jersey_scoped_request($requestId) : null;
        if (!$request) {
            flash_set('jersey_error', 'Request not found.', 'error');
            redirect($back);
        }

        $deptId = (int)$request['department_id'];
        $game = (string)$request['game_name'];
        $event = (string)$request['event_label'];
        $ay = (string)$request['academic_year'];
        $back = jersey_back_url($game, $event, $ay, $deptId);

        if ((int)$request['locked']) {
            flash_set('jersey_error', 'This request is locked and cannot be changed.', 'error');
            redirect($back);
        }

        if ($action === 'approve') {
            $finalNumber = (int)($request['final_number'] ?: $request['preferred_number']);
            $conflict = db_one(
                'SELECT id FROM jersey_requests
                  WHERE jersey_form_id = ? AND id <> ? AND status = \'Approved\'
                    AND COALESCE(final_number, preferred_number) = ?',
                [(int)$request['jersey_form_id'], $requestId, $finalNumber],
                'iii'
            );
            if ($conflict) {
                flash_set('jersey_error', "Jersey number $finalNumber is already approved for another player.", 'error');
                redirect($back);
            }
            db_execute(
                'UPDATE jersey_requests SET status = \'Approved\', final_number = ? WHERE id = ?',
                [$finalNumber, $requestId],
                'ii'
            );
            flash_set('jersey_ok', 'Request approved.', 'success');
            redirect($back);
        }

        if ($action === 'reject') {
            db_execute('UPDATE jersey_requests SET status = \'Rejected\' WHERE id = ?', [$requestId], 'i');
            flash_set('jersey_ok', 'Request rejected.', 'success');
            redirect($back);
        }

        if ($action === 'edit_number') {
            $newNumber = (int)($_POST['final_number'] ?? 0);
            if ($newNumber < 1 || $newNumber > 99) {
                flash_set('jersey_error', 'Jersey number must be between 1 and 99.', 'error');
                redirect($back);
            }
            $conflict = db_one(
                'SELECT id FROM jersey_requests
                  WHERE jersey_form_id = ? AND id <> ? AND status <> \'Rejected\'
                    AND COALESCE(final_number, preferred_number) = ?',
                [(int)$request['jersey_form_id'], $requestId, $newNumber],
                'iii'
            );
            if ($conflict) {
                flash_set('jersey_error', "Jersey number $newNumber is already taken.", 'error');
                redirect($back);
            }
            db_execute('UPDATE jersey_requests SET final_number = ? WHERE id = ?', [$newNumber, $requestId], 'ii');
            flash_set('jersey_ok', "Jersey number updated to $newNumber.", 'success');
            redirect($back);
        }

        db_execute('UPDATE jersey_requests SET locked = 1 WHERE id = ?', [$requestId], 'i');
        flash_set('jersey_ok', 'Request locked. No further changes are allowed.', 'success');
        redirect($back);

    case 'delete_all':
        if ($game === '' || $event === '' || $deptId <= 0) {
            flash_set('jersey_error', 'A valid team is required.', 'error');
            redirect('jersey_dashboard.php');
        }
        jersey_require_team($game, $event, $ay, $deptId);
        $form = db_one(
            'SELECT id FROM jersey_forms
              WHERE department_id = ? AND game_name = ? AND event_label = ? AND academic_year = ?',
            [$deptId, $game, $event, $ay],
            'isss'
        );
        if ($form) {
            db_execute(
                'DELETE FROM jersey_forms WHERE id = ? AND department_id = ?',
                [(int)$form['id'], $deptId],
                'ii'
            );
            flash_set('jersey_ok', 'All jersey data for this team has been deleted.', 'success');
        } else {
            flash_set('jersey_error', 'No jersey form was found for this team.', 'error');
        }
        redirect('jersey_dashboard.php');

    default:
        http_response_code(400);
        exit('Unknown action.');
}
