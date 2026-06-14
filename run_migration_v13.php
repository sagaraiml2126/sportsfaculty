<?php
/**
 * One-time migration runner for jersey tables.
 * Run: php run_migration_v13.php
 */
require_once __DIR__ . '/includes/bootstrap.php';

$sql = file_get_contents(__DIR__ . '/sql/migration-v13-jersey.sql');

$conn = db();
$result = $conn->multi_query($sql);

if ($result) {
    do {
        if ($r = $conn->store_result()) {
            $r->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
} else {
    echo "Migration v13 (Jersey Kit) completed successfully.\n";
}
