# Security design and operational responsibilities

No software can be guaranteed secure against every attack. This MVP includes the following implemented controls; deploying and operating it safely still requires secure infrastructure, updates, backups and independent testing. It has not received a penetration test or compliance certification.

## Implemented controls

- Passwords use Laravel hashing; staff passwords are never displayed. Passwords need 12+ characters, upper/lowercase, numbers and symbols. Temporary/reset passwords must be replaced before other modules can be used.
- Login has a normalized identity/IP limiter of 5 failed attempts per 60 seconds, plus route-level throttling. Login errors do not distinguish unknown users from wrong passwords or inactive accounts.
- Session ID regeneration on login/password change; logout invalidates the session and rotates CSRF token. Database sessions support revocation. Inactive users are denied on every request. Password resets/role changes revoke sessions; changing your own password revokes other sessions.
- Laravel CSRF protection applies to mutations, including sign-in. Logout is not exposed as GET. Cookies are HttpOnly and SameSite=Lax; session payload encryption is enabled in the example configuration. Secure cookies and HSTS apply when configured/deployed on HTTPS.
- Controller permissions enforce RBAC regardless of which buttons are visible. Technicians additionally need job assignment. Resource table/column names are allowlisted. Unknown resources return 404.
- Query builder parameter binding avoids concatenating submitted values into SQL. A local-only database creation command validates the database identifier before quoting it.
- Blade output escaping mitigates HTML/script injection. No user-provided HTML is rendered raw. CSP restricts scripts/styles/images to this origin, disallows objects/framing and restricts forms/base URLs. Defensive frame, MIME-sniffing, referrer and permissions headers are set.
- Every submitted mutation is validated and uses an explicit subset of allowed fields. Users cannot assign their own role or update invoice totals via unrecognized input fields.
- Logo uploads require a recognized image, accepted MIME/extension, max 2 MB and bounded dimensions. GD re-encodes to a random-name PNG to discard embedded metadata/payloads. SVG uploads are not accepted; only the developer-provided sample SVG is served.
- Approval locks estimate lines. Server-calculated integer-money totals, foreign keys, unique invoice linkage, row locks, nonnegative stock checks and idempotency keys protect core operational integrity.
- Stock deductions, invoice creation, payments and audit writes use transactions. Payment rows cannot be edited/deleted through the application. Sensitive linked history cannot be deleted through CRUD routes.
- CSV exports neutralize cells beginning with spreadsheet formula prefixes. Exports are role-protected and audited.
- Friendly 403/404/419/500 views avoid exposing internals when APP_DEBUG=false. Protected logs retain diagnostic information.
- Staff mutations, sign-in/out, password changes, stock changes, work order actions, payment recording and exports create audit events. Audit does not store submitted passwords or full request bodies.

## Role matrix

W = create/update/delete where lifecycle permits; R = read; A = assigned work orders only.

| Area | Admin | Manager | Advisor | Technician | Cashier |
|---|---|---|---|---|---|
| Overview | R | R | R | R (assigned job totals) | R |
| Customers | W | W | W | — | R |
| Vehicles | W | W | W | R | R |
| Appointments | W | W | W | — | — |
| Work orders / estimates / approval | W | W | W | R (A) | R |
| Findings / start / complete | W | W | W | W (A) | — |
| Parts & stock | W | W | R | R | — |
| Service catalog | W | W | R | R | — |
| Suppliers | W | W | — | — | — |
| Invoices / payments | W | W | R | — | W |
| Reports / payment export | R | R | — | — | R |
| Full audit log | R | R | — | — | — |
| Business settings / staff | W | — | — | — | — |

Technicians may see the vehicle directory and current stock, including displayed prices. Vehicle history is filtered to their assigned work orders; customer contact data is shown on their assigned jobs. All employees belong to one trusted business; there is no per-customer data partition. Advisor work permissions include repair progress as well as reception, by explicit policy. Fixed roles can be changed by editing `Access.php` and updating tests; arbitrary custom permissions are not exposed in the UI.

## Boundaries and deployment responsibilities

- Demo credentials are public local fixtures. Never seed them into an internet-facing deployment. Use a clean production database and individually created accounts.
- Set APP_DEBUG=false and keep `.env`, source, vendor, tests, backups and logs outside the document root. Only `public/` may be web-accessible.
- Terminate HTTPS correctly and set SESSION_SECURE_COOKIE=true. Local HTTP intentionally uses non-Secure cookies. Do not trust arbitrary forwarded headers.
- Use a dedicated non-root MySQL user, private network access, OS access controls and restricted writable directories. Protect the application key and backup encryption material.
- Audit records are application append-only, not tamper-evident against DB/server administrators. Failed login attempts are throttled but do not create a dedicated security-event ledger. Add centralized log monitoring and alerting for production.
- This release has no MFA, SSO, antivirus scanning, WAF, automated backup scheduler, automated retention erasure or recovery-email flow. Add these where the business threat model requires them.
- Store no card data, identity documents or secrets in free-text notes. This is not a payment processor, general ledger, tax compliance engine or jurisdiction-certified receipt system.
- Customer authorization is entered by staff after offline consent; it is not a cryptographic signature or independently verified customer approval.
- Browser security checks and dependency advisory scans supplement tests; they do not prove absence of vulnerabilities. Keep PHP, MySQL and Composer dependencies supported and patched. Review business rules and data privacy obligations for the deployment location.

## Verification and incident handling

Run automated tests and `composer audit` after dependency or security changes. Use a disposable `_test` database for integration tests. Before public exposure, commission independent review of authorization, session handling, uploads, infrastructure and concurrent financial operations. If compromise is suspected: restrict network access, preserve logs/backups, revoke affected sessions/accounts, investigate the cause and restore only from a verified clean state. Do not rotate APP_KEY casually: encrypted sessions and backups may depend on it.
