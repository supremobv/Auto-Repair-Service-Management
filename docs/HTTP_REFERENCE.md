# HTTP and integration reference

This release is a server-rendered application. It does **not** expose a versioned public REST API, API keys or token-authenticated integrations. The following routes document the actual internal browser contract for developers. Do not present these as a third-party API. All operational routes require an active session and completion of temporary-password onboarding.

## Authentication and transport

Sign in with POST `/login` using `email`, `password` and the CSRF token from the login form. Laravel issues an HttpOnly session cookie and regenerates the session ID. Mutating browser forms send `_token`; PUT and DELETE HTML forms POST with `_method=PUT` / `_method=DELETE`. Logout is POST only. No cross-origin access is configured. Use HTTPS for deployment.

Routes typically return HTML or a redirect after a successful write. Validation redirects back with session errors and old input. When requesting JSON using `Accept: application/json`, Laravel renders validation errors as 422 JSON, unauthenticated access as 401, authorization failures as 403, missing resources as 404, CSRF failures as 419 and rate limits as 429. There is no stable JSON success-resource contract. Collections render pagination links and preserve query filters.

## Route table

| Method | Path | Permission | Purpose / important fields |
|---|---|---|---|
| GET | `/login` | Guest | Sign-in form |
| POST | `/login` | Guest, throttled | `email`, `password` |
| POST | `/logout` | Signed in | End and invalidate session |
| GET/POST | `/password` | Signed in | Form / `current_password`, `password`, `password_confirmation` |
| GET | `/` | dashboard | Role-scoped overview |
| GET | `/orders` | orders.read | `q`, `status`, `page` |
| GET | `/orders/create` | orders | Intake form |
| POST | `/orders` | orders | `vehicle_id`, optional `technician_id`, `concern`, `priority`, optional `due_date`, `mileage` |
| GET | `/orders/{id}` | orders.read + assignment | Job, estimate, next steps |
| PUT | `/orders/{id}` | orders.work + assignment | `diagnosis`, `inspection`; closed jobs rejected |
| PUT | `/orders/{id}/assignment` | orders | optional `technician_id`, `priority`, optional `due_date`; open jobs only |
| POST | `/orders/{id}/items` | orders | optional `part_id` or `service_id`; custom `description` and `price` otherwise; `quantity` |
| DELETE | `/orders/{id}/items/{item}` | orders | Draft line deletion; item must belong to the job |
| POST | `/orders/{id}/status` | orders or orders.work | `status`, `approval_name` required for approval |
| POST | `/orders/{id}/invoice` | billing | `due_date`; completed job only |
| GET | `/invoices` | billing.read | `q`, `status=paid/outstanding/overdue`, `page` |
| GET | `/invoices/{id}` | billing.read | Printable invoice, receipt history |
| POST | `/invoices/{id}/payments` | billing, throttled | `amount`, `method`, optional `reference`, UUID `idempotency_key` |
| GET | `/manage/{resource}` | resource.read | `q`, `page`; appointment `status`, inventory `low=1` |
| GET | `/manage/{resource}/create` | resource write | Create form |
| POST | `/manage/{resource}` | resource write | Resource fields below |
| GET | `/manage/{resource}/{id}/edit` | resource write | Edit form |
| PUT | `/manage/{resource}/{id}` | resource write | Update validated fields |
| DELETE | `/manage/{resource}/{id}` | resource write | Delete unused record; foreign-key history protected |
| GET | `/vehicles/{id}/history` | vehicles.read | Service history; technicians see only their assigned jobs |
| POST | `/inventory/{id}/stock` | inventory | integer signed `quantity`, `reason` (5–200 chars) |
| GET | `/stock-movements` | inventory.read | Paginated stock ledger |
| GET/POST | `/settings` | admin only | Business configuration, optional multipart logo |
| GET/POST | `/staff` | admin only | List / create account |
| PUT | `/staff/{id}` | admin only | Update account, role, activation or reset password |
| GET | `/audit` | audit | Read-only event list |
| GET | `/reports` | reports | Optional ISO `from` / `to` dates |
| GET | `/reports/export` | reports | Required `from` / `to`; streamed CSV |
| GET | `/help` | Signed in | User manual |
| GET | `/up` | Public | Laravel application liveness endpoint; does not validate all business services |

## Allowlisted resources and fields

Only these identifiers are accepted; arbitrary table names fail with 404.

| Resource | Fields |
|---|---|
| customers | `name`, `email?`, `phone`, `address?`, `notes?` |
| vehicles | `customer_id`, `plate`, `make`, `model`, `year`, `vin?`, `mileage`, `notes?` |
| appointments | `vehicle_id`, `technician_id?`, `service`, `starts_at`, `ends_at`, `status`, `notes?` |
| inventory | `sku`, `name`, `supplier_id?`, `cost`, `price`, `reorder_level`; stock uses the separate ledger route |
| services | `name`, `description?`, `price`, `duration_minutes` |
| suppliers | `name`, `email?`, `phone?`, `address?` |

Settings fields: `name`, `tagline?`, `email?`, `phone?`, `address?`, `currency`, `tax_rate`, `invoice_footer?`, optional logo upload and `remove_logo=1`. Staff fields: `name`, `email`, `role`, boolean `active`, and required initial / optional reset `password`. `?` denotes optional, not a literal field suffix. Unknown fields are discarded rather than mass-assigned.

## Enumerations

- Roles: admin, manager, advisor, technician, cashier.
- Priority: normal, high, urgent.
- Work order: estimate, approved, in_progress, completed, invoiced, cancelled; only documented transitions allowed.
- Appointment: scheduled, checked_in, completed, cancelled, no_show.
- Payment method: cash, bank_transfer, card, ewallet. All record externally received funds.
- Currency: PHP, USD, SGD, EUR, GBP, AUD, CAD, MYR; one currency per installation.

Money input is decimal with at most two places. Internal cents, tax basis points, paid totals and document numbers are always server-generated. Never send card details. Date/time entry uses the configured workshop timezone. Payment idempotency keys are generated per form, and retries of that same form reuse the key. A unique database constraint provides an additional duplicate guard.

## References

The implementation uses Laravel's official [database and transaction facilities](https://laravel.com/docs/12.x/database) and [session authentication](https://laravel.com/docs/12.x/authentication). This is internal project documentation, not a promise that HTML form endpoints are a stable external API.
