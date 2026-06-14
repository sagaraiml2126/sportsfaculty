# College Sports Faculty Portal

Production installation guide for Apache/cPanel, PHP, and MySQL/MariaDB.

## Requirements

- PHP 8.1 or newer
- Extensions: `mysqli`, `mbstring`, `fileinfo`, `zip`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `.htaccess` support
- HTTPS in production

The application is server-rendered PHP. There is no separate frontend build and
no `index.html`; `index.php` is the public entry point.

## Fresh cPanel Installation

1. Create a MySQL database and database user in cPanel.
2. Grant the user all privileges on that database.
3. Select that database in phpMyAdmin.
4. Import `sql/schema.sql`.
5. Import `sql/seed.ready.sql`.
6. Upload the application files to `public_html/` or a subdirectory.
7. Copy `includes/config.local.php.example` to
   `includes/config.local.php`.
8. Fill in the real database credentials, `APP_ENV`, and `SITE_URL`.
9. Confirm `uploads/` is writable by PHP, normally permission `755` or `775`.
10. Visit `faculty-login.php` and change every temporary password immediately.

Do not edit `includes/db.php` with production credentials. The untracked
`config.local.php` file is the private deployment configuration.

Example:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'account_sports');
define('DB_USER', 'account_sportsuser');
define('DB_PASS', 'strong-database-password');
define('APP_ENV', 'production');
define('SITE_URL', 'https://www.example.edu/sports');
```

`SITE_URL` must exactly match the public installation path, with no trailing
slash. It is used for password-reset and jersey-form links.

## Existing Installation Upgrade

Back up the database and `uploads/` first. Import only migrations not previously
applied, in numeric order. The latest migrations are:

- `migration-v14-fix-faculty-departments.sql`
- `migration-v15-jersey-department-scope.sql`
- `migration-v16-fix-seed-data.sql`
- `migration-v17-contact-messages.sql`

Migration v15 changes jersey forms to department-scoped records. Apply it
before deploying the matching PHP code.

## Default Accounts

| Username | Temporary password | Access |
|---|---|---|
| `admin` | `Admin@123` | Super admin |
| `eng_faculty` | `Faculty@123` | Engineering and Pharmacy |
| `poly_faculty` | `Faculty@123` | Polytechnic and D.Pharm |
| `pharm_faculty` | `Faculty@123` | MBA, MCA, BBA, BCA, Architecture |

Freshly seeded accounts are forced to choose a new password before entering the
portal.

## Files to Upload

Upload:

- `admin/`, `api/`, `css/`, `images/`, `includes/`, `uploads/`, `vendor/`
- Root PHP files
- Root `.htaccess`

Keep the `sql/` folder outside the public web root when possible. If it is
uploaded, its `.htaccess` blocks direct access.

Do not upload:

- `.git/`, `.github/`, `.codex/`
- Local logs, temporary files, screenshots, or database backups
- `College_Sports_Portal_cPanel_Deployment_Guide.pdf`
- Any local `includes/config.local.php` containing development credentials

Create the production `config.local.php` directly on the server.

## Production Checks

1. Public homepage, notices, achievements, and images load over HTTPS.
2. Wrong credentials are rejected and login rate limiting works.
3. Each faculty account sees only its assigned departments.
4. Student create, edit, delete, photo, and document uploads work.
5. Provisional lists import into final teams without duplicates.
6. Word and spreadsheet exports download and open correctly.
7. Jersey links accept only players from the matching department and team.
8. Notice PDF and achievement image uploads reject invalid file types.
9. Public contact submissions appear in the super-admin Contact Messages inbox.
10. `includes/`, `sql/`, and PHP files inside `uploads/` return 403.
11. PHP error display is off in production and errors are written to the server
    log with a reference ID.

## Local XAMPP

Create a database named `csf_portal`, then:

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root csf_portal < sql\schema.sql
& C:\xampp\mysql\bin\mysql.exe -u root csf_portal < sql\seed.ready.sql
```

Local defaults are `127.0.0.1`, user `root`, blank password, database
`csf_portal`. Open:

`http://localhost/college-sports-faculty/`

## Backups

Back up both the database and the complete `uploads/` directory. Database-only
backups do not contain student documents, photos, notice PDFs, or achievement
images.
