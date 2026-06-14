<?php
/**
 * File upload helper. Validates MIME, size, and renames to a safe filename.
 * Used by student_save.php and notice_save.php.
 */

declare(strict_types=1);

const UPLOAD_BASE = __DIR__ . '/../uploads';

// Whitelist of allowed upload buckets. Anything outside this list is rejected
// to prevent path traversal (e.g., $bucket = 'students/../../etc').
const UPLOAD_BUCKETS = ['students', 'notices', 'achievements', 'documents'];

function upload_dir(string $bucket): string
{
    if (!in_array($bucket, UPLOAD_BUCKETS, true)) {
        throw new InvalidArgumentException("Invalid upload bucket: $bucket");
    }
    $dir = UPLOAD_BASE . '/' . $bucket;
    if (!is_dir($dir)) {
        // Single atomic-ish create: suppress warnings, but verify result.
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create upload directory: $dir");
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("Upload directory is not writable: $dir");
    }
    return $dir;
}

function upload_error_message(string $code): string
{
    if (str_starts_with($code, 'upload_error_')) {
        return match ((int)substr($code, 13)) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload.',
            default => 'The upload could not be completed.',
        };
    }
    return match ($code) {
        'too_large' => 'The file is larger than the allowed size.',
        'bad_extension', 'bad_mime', 'not_an_image' => 'The selected file type is not allowed.',
        'move_failed' => 'The server could not store the uploaded file.',
        'no_file' => 'No file was selected.',
        default => 'The upload could not be completed.',
    };
}

/**
 * Delete a stored upload only when its resolved path is inside the expected
 * upload bucket. Invalid, missing, and already-deleted paths are ignored.
 */
function delete_uploaded_file(?string $relative_path, string $bucket): bool
{
    if (!$relative_path || !in_array($bucket, UPLOAD_BUCKETS, true)) {
        return false;
    }

    $bucket_root = realpath(UPLOAD_BASE . '/' . $bucket);
    $target = realpath(__DIR__ . '/../' . ltrim(str_replace('\\', '/', $relative_path), '/'));
    if ($bucket_root === false || $target === false || !is_file($target)) {
        return false;
    }

    $prefix = rtrim($bucket_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $inside_bucket = DIRECTORY_SEPARATOR === '\\'
        ? str_starts_with(strtolower($target), strtolower($prefix))
        : str_starts_with($target, $prefix);

    return $inside_bucket && @unlink($target);
}

/**
 * Validate and store a single uploaded image.
 *
 * @param string      $bucket  'students' or 'notices'
 * @param array|null  $file    the $_FILES[…] entry; if null, returns null (no upload)
 * @param int         $max_kb  size limit
 * @param string[]    $allowed_extensions e.g. ['jpg','jpeg','png','webp']
 * @return array{ok:true,path:string,filename:string}|array{ok:false,error:string}
 */
function handle_image_upload(string $bucket, ?array $file, int $max_kb = 2000, array $allowed_extensions = ['jpg','jpeg','png','webp']): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'no_file'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_error_' . (int)$file['error']];
    }
    if ($file['size'] > $max_kb * 1024) {
        return ['ok' => false, 'error' => 'too_large'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions, true)) {
        return ['ok' => false, 'error' => 'bad_extension'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'not_an_image'];
    }
    $image_extensions = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG  => ['png'],
        IMAGETYPE_WEBP => ['webp'],
    ];
    $detected_extensions = $image_extensions[$info[2] ?? 0] ?? [];
    if (!in_array($ext, $detected_extensions, true)) {
        return ['ok' => false, 'error' => 'bad_mime'];
    }
    $safe_name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest_dir  = upload_dir($bucket);
    $dest_path = $dest_dir . '/' . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['ok' => false, 'error' => 'move_failed'];
    }
    @chmod($dest_path, 0644);
    return ['ok' => true, 'path' => 'uploads/' . $bucket . '/' . $safe_name, 'filename' => $safe_name];
}

/**
 * Generic document upload (PDF, JPG, PNG).
 */
function handle_generic_document_upload(string $bucket, ?array $file, array $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'], int $max_kb = 5000): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'no_file'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_error_' . (int)$file['error']];
    }
    if ($file['size'] > $max_kb * 1024) {
        return ['ok' => false, 'error' => 'too_large'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException('The PHP fileinfo extension is required for uploads.');
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mimes, true)) {
        return ['ok' => false, 'error' => 'bad_mime'];
    }

    $ext = match($mime) {
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        default           => 'bin',
    };

    $safe_name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest_dir  = upload_dir($bucket);
    $dest_path = $dest_dir . '/' . $safe_name;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['ok' => false, 'error' => 'move_failed'];
    }
    @chmod($dest_path, 0644);
    return ['ok' => true, 'path' => 'uploads/' . $bucket . '/' . $safe_name, 'filename' => $safe_name];
}

/**
 * PDF upload for notices.
 */
function handle_pdf_upload(string $bucket, ?array $file, int $max_kb = 5000): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'no_file'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_error_' . (int)$file['error']];
    }
    if ($file['size'] > $max_kb * 1024) {
        return ['ok' => false, 'error' => 'too_large'];
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        return ['ok' => false, 'error' => 'bad_extension'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException('The PHP fileinfo extension is required for uploads.');
    }
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'application/pdf') {
        return ['ok' => false, 'error' => 'bad_mime'];
    }
    $safe_name = bin2hex(random_bytes(8)) . '.pdf';
    $dest_dir  = upload_dir($bucket);
    $dest_path = $dest_dir . '/' . $safe_name;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['ok' => false, 'error' => 'move_failed'];
    }
    @chmod($dest_path, 0644);
    return ['ok' => true, 'path' => 'uploads/' . $bucket . '/' . $safe_name, 'filename' => $safe_name];
}
