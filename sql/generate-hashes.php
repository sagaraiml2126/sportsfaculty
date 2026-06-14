<?php
/**
 * One-shot helper: generate bcrypt hashes for seed.sql, then import the schema + seed.
 * Run from project root in a terminal that has PHP + MySQL available.
 *
 *   php sql/generate-hashes.php
 *
 * It will:
 *   1. Generate bcrypt hashes for the two default passwords.
 *   2. Write the final seeded .sql into sql/seed.ready.sql (next to seed.sql).
 *
 * Safe to delete after first use.
 */

if (PHP_SAPI !== 'cli') {
    die("Run from command line: php sql/generate-hashes.php\n");
}

$admin_pw  = 'Admin@123';
$faculty_pw = 'Faculty@123';

$admin_hash  = password_hash($admin_pw,  PASSWORD_BCRYPT, ['cost' => 12]);
$faculty_hash = password_hash($faculty_pw, PASSWORD_BCRYPT, ['cost' => 12]);

echo "Admin hash  ($admin_pw):  $admin_hash\n";
echo "Faculty hash ($faculty_pw):  $faculty_hash\n";

$template = file_get_contents(__DIR__ . '/seed.sql');
$ready = str_replace(
    ['__ADMIN_HASH__',  '__FACULTY_HASH__'],
    [$admin_hash,      $faculty_hash],
    $template
);
file_put_contents(__DIR__ . '/seed.ready.sql', $ready);
echo "Wrote sql/seed.ready.sql\n";

// Offer to import
echo "\nTo import now, run:\n";
echo "  mysql -u root -p your_database < sql/schema.sql\n";
echo "  mysql -u root -p your_database < sql/seed.ready.sql\n";
echo "Or select your database in phpMyAdmin, then import schema.sql and seed.ready.sql.\n";
