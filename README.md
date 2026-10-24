# Torque Workshop — Auto Repair & Service Management

A working, single-location workshop MVP built with **Laravel 12, PHP 8.2+, MySQL, Blade, plain CSS and JavaScript**. The interface is server-rendered, with no frontend build step or external CDN. Designed for Laragon on Windows.

## Open the installed application

The workspace has been configured against local MySQL database `torque_workshop`, migrated and populated with fictional sample data.

```powershell
composer dev
```

Open **http://127.0.0.1:8000**. If a preview server is already running, simply open that address.

Sample administrator: **admin@torque.test** / **TorqueDemo!2026**. The application requires a new password at first sign-in. Other sample roles use `manager@torque.test`, `advisor@torque.test`, `technician@torque.test`, `cashier@torque.test`, with the same temporary password. These are local demo credentials, not production accounts.

## Features

- Dashboard with live operational totals, appointments, low stock and activity.
- Customer directory and vehicle registration, odometer readings and service history.
- Appointments with technician assignment and overlapping-booking checks.
- Work orders with priority, due date, reassignment, customer concerns, diagnosis and inspection notes.
- Estimates with catalog labor, inventory parts, custom labor, quantities and tax; recorded customer authorization locks prices.
- Controlled lifecycle: estimate → approved → in progress → completed → invoiced. Pre-start cancellation.
- Inventory receipts/adjustments, low-stock monitoring, suppliers and traceable stock movements. Parts deduct when repairs start.
- Immutable issued invoices, partial/full payments, duplicate-submission protection, balances, overdue status and receipt references.
- Printable estimates, job sheets and invoices with payment history; browser Save as PDF.
- Reports by period, receivables, overdue balances, inventory valuation and payment CSV export.
- Five server-enforced roles, inactive-account enforcement, temporary password changes, login throttling and audit logs.
- Customizable business name, original sample SVG logo or uploaded logo, contact details, currency, default tax and invoice footer.
- Friendly validation/errors, search, filters, pagination, responsive layouts and an in-app user manual.

## Fresh installation

1. Start MySQL in Laragon. Use PHP 8.2+ with PDO MySQL, mbstring, OpenSSL, fileinfo, GD, XML, ctype, tokenizer, curl and zip. Composer is required; Node is not required.
2. Copy `.env.example` to `.env`, and set your MySQL host, port, database, username and password. Use a **new empty database** for a new installation. The local defaults use `127.0.0.1:3306`, database `torque_workshop`, root and an empty password, matching a typical local Laragon setup.
3. Run `powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1` for a clean business installation, or add `-Demo` to populate an empty local database with sample data. The script preserves an existing `.env` and application key; it does not reset a database.
4. For a clean installation, run `php artisan workshop:admin owner@example.com --name="Workshop Owner"`. Enter a strong temporary password at the hidden prompt.
5. Run `composer dev` or `powershell -File .\scripts\start.ps1`.
6. Sign in, change your temporary password, set Business settings, then add staff, services, suppliers and opening inventory.

See [Laragon setup](docs/SETUP.md) for Apache virtual hosts, troubleshooting, backup and deployment.

## Documentation

- [Installation, database, operations and deployment](docs/SETUP.md)
- [User manual](docs/USER_MANUAL.md) — also available under Help & user manual in the application
- [Architecture and data model](docs/ARCHITECTURE.md)
- [HTTP route and integration reference](docs/HTTP_REFERENCE.md)
- [Security controls, role matrix and boundaries](docs/SECURITY.md)
- [Scope, limitations and validation](docs/ACCEPTANCE.md)

## Verification

```powershell
php artisan test
powershell -File .\scripts\test-mysql.ps1
composer audit
```

The default suite uses isolated in-memory SQLite. The second command uses the **disposable** MySQL database `torque_workshop_test`; it never intentionally targets your working database. Both exercise the same workflows. Test startup refuses persistent databases whose names do not end in `_test`.

## Project map

```text
app/Http/Controllers/   Authentication, workshop resources, orders, billing, administration
app/Http/Middleware/    Session enforcement and security response headers
app/Support/           Role policy, resource allowlist, currency math and audit helpers
database/migrations/   Portable MySQL schema with foreign keys and indexes
database/seeders/      Safe business defaults and opt-in local demo data
resources/views/       Blade layouts, pages, printable documents and friendly errors
public/app.css         Responsive UI, components and print stylesheet
public/app.js          Small progressive UI enhancements
public/logo.svg        Original replaceable sample business mark
routes/                Web routes and administrative console commands
scripts/               Windows setup, start and isolated MySQL test commands
tests/                 Security and business workflow integration tests
docs/                  Technical documentation and user manual
```

This MVP records payments taken through external channels; it does not process cards or store card numbers. Refunds/credit notes, purchase-order workflows, customer self-service, SMS/email sending, multi-branch operation and jurisdiction-specific accounting certification are outside this release. No claim of immunity to every attack or regulatory certification is made.
