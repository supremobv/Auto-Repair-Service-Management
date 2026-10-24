# MVP scope and validation record

## Included acceptance areas

| Requirement | Delivered implementation |
|---|---|
| Traditional MySQL / Laragon | `.env` MySQL configuration; migrations applied on local MySQL; setup script and Apache guide |
| Business customization | Name, logo upload/reset, tagline, address, contacts, one supported currency, default tax and invoice instructions |
| Modern usable interface | Responsive sidebar/dashboard, clear forms, inline stage controls, search/filter/pagination, print layouts and friendly error pages |
| Reception | Customer directory, vehicle profiles/history, appointments, technician schedule conflict detection |
| Repair operations | Work orders, technician assignment/reassignment, priority/due date, estimates, offline authorization record, parts/labor, notes/inspection, progress |
| Financial operations | Unique issued invoices, captured tax/totals, business/customer snapshots, partial/full payment records, receipt identifiers, balances and overdue filters |
| Inventory | Suppliers, part pricing/cost, opening receipts, signed adjustments, work-order deductions, low-stock watch and movement ledger |
| Reporting | Period invoices/collections, current receivables/overdue/stock value, pipeline, streamed payment CSV |
| RBAC | Five enforced roles, record-level technician assignment, account activation, admin self/last-admin safeguards |
| Security | Hashing, onboarding password changes, throttles, CSRF, escaped output, bound queries, validated/re-encoded uploads, defensive headers and transaction checks |
| Documentation | Setup/operations, user guide, architecture/schema, HTTP reference, role/security details and scope limits |
| Original sample branding | Locally drawn SVG mark; replaceable in business settings; no scraped logo licensing dependency |

## Verification performed

Initial implementation verified on **2026-10-24**, using PHP 8.2.28 and the installed Laragon MySQL instance. The local working database is `torque_workshop`; tests run independently of it.

- Fast feature tests run on in-memory SQLite.
- The feature suite is also run against the separate `torque_workshop_test` MySQL database, including migrations, foreign-key protection and real MySQL transaction behavior.
- Tests cover guest/inactive/onboarding access, role denials, technician record isolation, representative page rendering, estimate immutability, correct cent/tax totals, complete repair/invoice/payment flow, duplicate invoices, repeated payment submissions, overpayments, stock shortage rollback, nonnegative stock, schedule conflicts, protected history, escaped script text, unknown resource rejection, self-demotion prevention, branding/currency restrictions, password changes and login throttling.
- Browser QA inspected the local sign-in, overview and estimate/assignment screen. Layout screenshot retained under `docs/screenshots/` when available.
- Composer dependency advisory audit run; see the current command output for the latest result.

Latest passing runs: **21 tests / 135 assertions** on SQLite; **20 feature tests / 134 assertions** on MySQL. The additional SQLite test is Laravel's scaffold unit smoke test. Composer audit reported no published security advisories at verification time.

Run `php artisan test` and `powershell -File scripts/test-mysql.ps1` to reproduce. The final delivery message reports the latest counts. Tests do not include production penetration testing, sustained load testing, parallel-process race stress tests, a complete mobile-device matrix or jurisdiction-specific accounting certification.

## Deliberate MVP limits

This is an internal, single-location application. No multi-branch/tenant isolation, customer portal, SMS/email delivery, photo/file attachments, time-clock payroll, formal procurement/purchase orders, supplier payables, accounting integration, discounts, multiple tax classes, fractional quantities, automated refunds/credit notes, post-start cancellation, electronic signatures, warranty/insurance workflow, scheduled maintenance reminders or public token API is included. Approved scope changes require a new separately authorized work order. Payments are manually recorded after external receipt, not processed online. Reports are operational, not statutory accounts. No universal legal/security compliance is claimed.

Records with business history are intentionally protected from deletion. Issued invoices are immutable rather than providing a partial correction workflow that could corrupt reconciliation. Any production adoption should explicitly review whether these limits match the workshop’s procedures.
