# Installation and operations

## Requirements

PHP 8.2+; Composer 2; MySQL 8 recommended (schema also uses standard MySQL-compatible features). PHP extensions: pdo_mysql, mbstring, openssl, tokenizer, XML/DOM, ctype, fileinfo, GD, curl and zip. Laravel 12 is selected to support the installed PHP 8.2 runtime. Browser: a current Edge, Chrome, Firefox or Safari release.

The current workspace was installed against the MySQL server reachable at `127.0.0.1:3306`. The application uses a real database, not browser localStorage. No Node build, queue worker, mail provider or third-party account is needed for the included features.

## Manual setup

From the project root in a Laragon terminal or PowerShell:

```powershell
composer install --prefer-dist --no-interaction
Copy-Item .env.example .env
php artisan key:generate
```

Do not overwrite an existing `.env` or rotate the key of a running installation. Edit `.env`:

```dotenv
APP_NAME="Torque Workshop"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Singapore
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=torque_workshop
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
```

Root with an empty password is for a private Laragon development instance only. Use your configured credentials if different.

```powershell
php artisan workshop:create-local-database
php artisan migrate --seed
php artisan storage:link
php artisan workshop:admin owner@example.com --name="Workshop Owner"
php artisan serve --host=127.0.0.1 --port=8000
```

The database-creation helper only accepts a local host and safe database identifiers, and never drops an existing database. If your database user lacks CREATE privilege, create an empty database in HeidiSQL instead, then run migrations. `DatabaseSeeder` inserts business defaults if missing; it does not create public credentials. The admin command rejects existing emails and prompts for a password without echoing it.

For optional demo data, run `php artisan db:seed --class=DemoSeeder` **before adding users or customers**. It requires `APP_ENV=local` and an empty workshop. Every demo account must change its temporary password at first login. Do not use the demo database for real business records.

## Laragon Apache virtual host

The project can remain in its current Desktop folder. Point the virtual host DocumentRoot at the project's **public** directory, never the repository root. Example paths must match your actual folder:

```apache
<VirtualHost *:80>
    ServerName torque-workshop.test
    DocumentRoot "C:/Users/primo/Desktop/Auto Repair & Service Management/public"
    <Directory "C:/Users/primo/Desktop/Auto Repair & Service Management/public">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite`, add `127.0.0.1 torque-workshop.test` to the Windows hosts file if Laragon does not manage it, restart Apache, and set `APP_URL=http://torque-workshop.test`. Keep `.env` and `storage/` outside the web root. Do not serve the repository with a generic static web server. Laravel's `public/.htaccess` handles routing.

## First business setup

1. Sign in and replace the temporary password with at least 12 characters containing uppercase, lowercase, a number and a symbol.
2. In Business settings, set the currency **before** adding priced services, parts or work orders. There is no currency conversion. Currency becomes locked once monetary workshop records exist.
3. Upload a logo, update your address and contact details, choose your tax rate and write invoice payment instructions.
4. Create separate staff accounts for each person; assign the least privileged role required.
5. Add suppliers, parts, opening stock adjustments and the service catalog.
6. Add customers and vehicles, then schedule appointments and open work orders.

## Configuration and storage

- Business display data: Settings table and Business settings page.
- Database, URL, timezone, session/security flags: `.env`.
- Uploaded logo images: `storage/app/public/logos`; `public/storage` links to this directory.
- Original sample logo: `public/logo.svg`, drawn specifically for this sample.
- Logs: `storage/logs/laravel.log`. Do not publish logs or secrets.
- Sessions and login rate limits: database tables by default. Restarting PHP does not reset the shared limiter.
- Prior uploaded logos are retained so older invoice snapshots still render correctly.

After `.env` changes run `php artisan optimize:clear`. After source updates run migrations and refresh caches as needed. Do not run `migrate:fresh` against a working database.

## Backup and restore

Back up all three together: the MySQL database, `storage/app/public`, and `.env`/application key. Store backups outside the public directory with restricted permissions and encryption. Restrict access to customer and payment data. Define retention based on your business and local obligations.

Use HeidiSQL in Laragon: right-click the database → Export database as SQL, include structure and data, write to a protected backup location. For a consistent backup while staff are active, briefly use `php artisan down`, export the database and copy logo storage, then `php artisan up`. Restore into a **new** database first, copy storage, restore the original application key, point `.env` at the restored database and run `php artisan optimize:clear`. Verify customer counts, invoices, balances and images before switching users over. Test restores regularly; an untested backup is not verified recovery.

## Production deployment

Use a supported PHP web server and HTTPS, never `artisan serve`. Provision a dedicated MySQL account with only required application privileges; use a separate migration credential if possible. Restrict database networking. Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `APP_URL=https://your-domain`, a suitable log level and an appropriate timezone. Configure TLS at the trusted web server; trust proxy headers only from your own reverse proxy. Do not expose MySQL or a Laragon dashboard publicly.

Deploy dependencies with `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force`, `php artisan storage:link`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`. Give the web server write access only to storage and bootstrap/cache, keeping source and `.env` protected. Create real accounts without DemoSeeder. Monitor error logs, update dependencies and run independent security testing before internet exposure. Set scheduled backups and a tested restoration procedure outside the application.

## Troubleshooting

| Symptom | Action |
|---|---|
| Could not find driver | Enable pdo_mysql in the PHP version used by both CLI and Apache. |
| Connection refused | Start MySQL in Laragon; check host and port. |
| Access denied for database user | Correct DB_USERNAME/DB_PASSWORD and database grants; clear config cache. |
| Unknown database | Create the configured database, then run migrations. |
| No application encryption key | Run `php artisan key:generate` on a new installation only. |
| Uploaded logo returns 404 | Run `php artisan storage:link`; on Windows use Developer Mode or an elevated terminal if symlink creation is denied. |
| CSRF/session expired | Use one hostname consistently, reload, sign in, and ensure cookies are enabled. Set secure-cookie false only for local HTTP. |
| 500 page | Check protected Laravel log for the actual cause; keep debug off outside a private debugging session. |
| Existing email in admin CLI | Sign in with another admin and reset/deactivate the account in Team & access. The CLI intentionally does not overwrite accounts. |
| Demo seeding rejected | It requires an empty local database; choose a new database rather than deleting existing work. |
| Port 8000 is occupied | Use the existing preview or serve on 8001 and update APP_URL. |

## Tests

`php artisan test` uses in-memory SQLite. `powershell -File .\scripts\test-mysql.ps1` creates `torque_workshop_test` if missing and runs the feature suite against it. MySQL credentials come from your environment. The MySQL config forces the local host, port and test database; edit that config for a different **disposable** test instance. Tests use RefreshDatabase and can destroy test tables. Never place valuable data in a database ending `_test`. Application test boot refuses other persistent database names. Do not cache production configuration while running tests.
