# ITMS One — Deployment and Recovery Guide

Scope: single-server LAN deployment on Windows Server with Apache 2.4, PHP 8.5, MySQL 8.4. This guide covers hardening settings, LAN access, scheduled jobs, backup, and restore. It assumes the approved stack and the `itms_admin_db` database only.

## 1. Environment

| Component | Required version | Notes |
|---|---|---|
| PHP | 8.5.x stable | Extensions: mbstring, openssl, pdo_mysql, mysqlnd, fileinfo, zip, gd/intl as used by PDF generation |
| Composer | 2.x | Install dependencies with `composer install --no-dev --optimize-autoloader` |
| Node.js / npm | LTS | Required only for building frontend assets |
| MySQL | 8.4 LTS | Database `itms_admin_db`, client tools (`mysqldump`) on the server |
| Apache | 2.4 | Serve only the `public/` directory |
| Windows Task Scheduler | — | Runs the scheduler and the backup job |

Current development machine paths (adjust on the server): PHP `C:\php85\php.exe`, MySQL client `C:\mysql84\bin`, Apache `C:\xampp\apache\bin\httpd.exe`, MySQL port `3307`.

## 2. Installation

1. Copy the project to a fixed directory, for example `C:\itms-one`.
2. Copy `.env.example` to `.env` and set:

```dotenv
APP_NAME="ITMS One"
APP_ENV=production
APP_KEY=<php artisan key:generate --show>
APP_DEBUG=false
APP_URL=http://<server-lan-ip>:80
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=itms_admin_db
DB_USERNAME=<dedicated-app-user>
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
```

3. Create the database and a dedicated MySQL account limited to localhost:

```sql
CREATE DATABASE itms_admin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'itms_app'@'localhost' IDENTIFIED BY '<strong-password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON itms_admin_db.* TO 'itms_app'@'localhost';
FLUSH PRIVILEGES;
```

4. Run the build and migrations:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
```

5. Create the single application account through `php artisan tinker` or a seeder approved for the deployment. The initial release has no role-based access control; one account can access all modules.

## 3. LAN Access and Restrictions

- Clients reach the app in a browser at `http://<server-lan-ip>/`. No client software, database client, or shared folder is required.
- Apache: point `DocumentRoot` to `<project>/public` and set `AllowOverride All` so the bundled `.htaccess` handles rewrite rules. Example vhost:

```apache
<VirtualHost *:80>
    ServerName <server-lan-ip>
    DocumentRoot "C:/itms-one/public"
    <Directory "C:/itms-one/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

- Windows Firewall: allow inbound TCP 80 (and 443 if TLS is added) only from the LAN subnet, and block inbound access to MySQL port 3307.
- MySQL: set `bind-address=127.0.0.1` in `my.ini` so the database is reachable only from the server itself. Never expose MySQL to the LAN or the internet.
- Private files (uploaded contract attachments and generated PDF/DOCX documents) live in `storage/app`, outside the web root, and are served only through authenticated application routes.
- Recommended Apache security headers:

```apache
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

- Set `APP_DEBUG=false` in production. Validation and system errors are logged to `storage/logs` and shown as a generic message to the user.

## 4. Scheduled Jobs

Windows Task Scheduler entry `ITMS One Scheduler`:

- Program: `C:\php85\php.exe`
- Arguments: `artisan schedule:run`
- Working directory: `C:\itms-one`
- Trigger: daily, repeat every 1 minute.

The schedule in `routes/console.php` runs:

| Job | Time | Purpose |
|---|---|---|
| `recycle-bin:purge` | 01:00 daily | Permanently deletes expired recycle bin entries only when no protected history or dependency exists; blocked entries stay and are logged. |
| `contracts:sync-status` | 02:00 daily | Marks contracts past their end date as `Nonaktif`; archived contracts are never overwritten. |

Both commands also run on demand:

```powershell
php artisan recycle-bin:purge
php artisan contracts:sync-status
```

## 5. Backup

`scripts/backup.ps1` dumps the database and archives private files into a timestamped folder, keeping the newest 30 backups.

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup.ps1
```

- Default destination: `C:\ITMS-Backup` (override with `-Destination "D:\Backups"` or the `ITMS_BACKUP_PATH` environment variable).
- Output per run: `database.sql` plus `private-files.zip` (contents of `storage/app`).
- The script reads database credentials from `.env`; it never prints the password.

Schedule it in Task Scheduler, for example daily at 23:30:

- Program: `powershell`
- Arguments: `-ExecutionPolicy Bypass -File "C:\itms-one\scripts\backup.ps1"`

Store backups on a different disk or network share from the application when possible. Backups contain business documents and must be access-controlled like the application itself.

## 6. Restore

Recovery steps, in order:

1. Stop the scheduled jobs and, if the server is live, inform users before touching the database.
2. Restore the database from the chosen timestamp folder:

```powershell
C:\mysql84\bin\mysql.exe --host=127.0.0.1 --port=3307 --user=<db-user> --password itms_admin_db -e "SOURCE C:\ITMS-Backup\<timestamp>\database.sql"
```

3. Restore private files: extract `private-files.zip` into `<project>\storage\app`, replacing existing files.
4. Verify the schema matches the code and warm the caches:

```powershell
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
```

5. Verify with `php artisan test`, then open the login page from a LAN client and confirm the `/up` health check returns `200`.

## 7. Backup/restore verification record

- Backup: `powershell -ExecutionPolicy Bypass -File scripts\backup.ps1` completed and produced `database.sql` plus `private-files.zip`.
- Restore: the dump was imported into a scratch database; table count (29) and sample row counts matched the source (`contracts` 1/1, `users` 1/1). The scratch database was dropped afterwards.
- Re-run this check after any migration, dependency update, or restore-procedure change.

## 8. Hardening Review Results

- Login throttling: 6 failed attempts per minute per client (`throttle:6,1` on the login POST route); failures are logged with username and IP.
- Single account, no registration route, inactive accounts cannot log in.
- All application routes require authentication; private files are served through authorized routes with path-traversal rejection.
- Server-side validation on all inputs, including money decimals, quantity precision, file type (`pdf`) and upload size.
- Blocking business rules (finalization, revisions, archive, purge) preserve existing data and explain why an action is blocked.
- Recycle bin purge keeps protected dependencies and history and records the reason.
- Database errors are logged; users see generic messages. Document generation failures do not undo finalization.

## 9. Performance and Usability Checks

- Dashboard, lists, and activity history use paginated queries (15–20 rows per page) with `withQueryString`.
- Indexed lookups: document `status`, contract `end_date`, `lhstp_versions.lhstp_date`, activity `entity_type`/`entity_id`/`created_at`, recycle bin `expires_at`.
- Document generation is synchronous for the current volume; the scheduler avoids queues as agreed in the blueprint.
- Empty and error states exist on dashboard, lists, recycle bin, and activity history.
- Next improvement if data volume grows: move PDF/DOCX generation to a queued job and cache dashboard counts.

## 10. Acceptance Checklist

- [x] Backup and restore tested (section 7).
- [x] Expired recycle-bin items purged only when safe; protected dependencies/history remain.
- [x] LAN clients use browser access; MySQL bound to localhost only.
- [x] All required tests pass (`php artisan test`, `vendor/bin/pint --test`).
- [x] Deployment and recovery instructions documented (this guide).
