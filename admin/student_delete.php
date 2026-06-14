<?php
/**
 * Student delete. POST + CSRF + scope.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Method not allowed.');
}

require_login();
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_set('student_error', 'Invalid id.', 'error');
    redirect('../admin/student_list.php');
}

[$scope, $p, $t] = scope_sql_department('s');

$row = db_one("SELECT id, photo_path FROM students s WHERE s.id = ? $scope",
    array_merge([$id], $p), 'i' . $t);

if (!$row) {
    flash_set('student_error', 'Student not found.', 'error');
    redirect('../admin/student_list.php');
}

$document_paths = db_select(
    'SELECT file_path FROM student_documents WHERE student_id = ?',
    [$id],
    'i'
);

db_execute('DELETE FROM students WHERE id = ?', [$id], 'i');

delete_uploaded_file($row['photo_path'] ?? null, 'students');
foreach ($document_paths as $document) {
    delete_uploaded_file($document['file_path'] ?? null, 'documents');
}

flash_set('student_saved', 'Student deleted.', 'success');
redirect('../admin/student_list.php');
