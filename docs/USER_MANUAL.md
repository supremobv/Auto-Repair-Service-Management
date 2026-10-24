# Workshop user manual

## Sign in and navigate

Open the workshop URL supplied by your administrator. Use your own email and password. New and reset accounts must replace their temporary password before using the workshop. Repeated failed sign-ins trigger a cooldown. Ask your administrator to reset your password if necessary; this release does not send password-reset emails.

The sidebar contains only the modules your role may access. Overview summarizes current jobs, today’s appointments, low-stock parts and recent activity. Financial totals appear only for permitted roles. Technicians see their assigned jobs. Click your name near the bottom of the sidebar to change your password, or Sign out to end the session. The mobile menu button opens navigation on small screens.

## Customers and vehicles

1. Open Customers → Add customer. Enter name and phone; email, address and notes are optional.
2. Open Vehicles → Add vehicle. Select the customer, enter the unique license plate, make, model, year and odometer. VIN/chassis number is optional but must be unique when present.
3. Use Search to find records. Use Edit to update details, or History on a vehicle to view previous work orders and odometer readings.
4. Delete removes unused records only. Linked records cannot be deleted because they support service or financial history. A vehicle with work orders cannot change owners through this release; preserve the history and register a distinct record through an agreed business process instead.

## Appointments

Add the vehicle, requested service, start/end time and optional technician. Use local workshop time. End must be after start. The same technician cannot have overlapping scheduled or checked-in appointments; adjoining slots are allowed. A cancelled, completed or no-show booking does not reserve time.

Use Edit to reschedule or mark Scheduled, Checked in, Completed, Cancelled or No show. Scheduling is independent of work orders, so check-in does not automatically create a job. No SMS or email is sent by this version.

## Work orders and estimates

Create a work order from the sidebar or Overview. Select the customer’s vehicle and technician, set priority and target date, record the intake odometer and describe the concern. Intake odometer updates the vehicle profile. Open the job to see its estimate, findings, details and next available action. Administrators, managers and advisors can reassign an open job or change priority and target date using Assignment & schedule.

To build an estimate:

1. Choose an inventory part **or** a service from the catalog, enter quantity, and Add line item. Catalog price is set by the server; entering a custom price does not override it.
2. For custom labor, leave both catalog selections blank and provide the description and final unit price.
3. Quantity is a whole number. For half-hour labor, enter a custom labor line with the correct amount for that time and quantity 1.
4. Remove a line while the estimate is still a draft. Tax uses the rate captured when the work order was created, rounded once on the subtotal to the nearest minor currency unit.
5. Print estimate / job sheet to share a paper copy or save as PDF. Browser print settings can remove browser headers/footers if desired.

Obtain actual customer consent outside the application. Enter the approving customer’s name, then Record approval. This records the staff user, customer name and time, but is not an electronic signature or a customer portal. Approved prices are locked. A draft with no line items cannot be approved.

## Repair progress

The lifecycle is **Estimate → Approved → In progress → Completed → Invoiced**. The interface displays only the next actions allowed for the current stage and your role. Technician access is restricted to assigned jobs, including direct links.

- Start repairs deducts all listed inventory parts in one transaction. If any part has insufficient stock, no parts are deducted and the job stays Approved. Receive the missing stock and retry.
- Save diagnosis and inspection findings throughout the repair. The inspection field accepts a readable checklist, for example brakes, tires, fluids, lights and safety concerns.
- Mark completed after work and findings are ready. Completed jobs await an invoice.
- Estimate or Approved jobs can be cancelled; no stock has been consumed at those stages. In-progress jobs cannot be cancelled or returned to an earlier status in this release.
- Existing approved scope cannot be expanded. For additional authorized work, create a separate work order/estimate and follow your business approval process. Corrections, returns, credit notes and refunds are handled outside this MVP rather than rewriting history.

## Invoices and payments

An administrator, manager or cashier opens a completed job, sets the payment due date and chooses Issue invoice. Only one invoice can be issued for a work order. The invoice snapshots the business and customer details and locks its totals. Later business or customer changes do not rewrite it. Newly issued invoices also retain vehicle details; the initial demo invoices use the sample vehicle records.

Open Invoices & payments to search by number or customer and filter Paid, Outstanding or Overdue. Invoice detail shows line items, tax, total, payments and remaining balance. Record a payment **only after it was actually received** through cash, bank transfer, an external card terminal or an e-wallet. Choose the method and enter a transaction/receipt reference if available. This system does not charge a card or move money.

Partial payments are allowed. A payment greater than the outstanding balance is rejected. Repeating the same submitted payment form does not create a second payment. Each recorded payment has a receipt number. Print / save PDF prints the invoice with its payment history, so it can serve as an acknowledgement of recorded receipts. There is no payment delete or refund button.

## Inventory and suppliers

Create suppliers first, then add parts with unique SKU, cost, selling price and reorder level. New parts start at zero stock. To receive stock, open Edit / stock and submit a positive adjustment with a delivery or purchase reference. Enter a negative adjustment for shrinkage or a correction; explain the reason. Negative resulting stock is rejected.

Stock history records who changed quantities, why and when. Starting a repair creates automatic negative movements linked by work order number. Low stock means stock is at or below its reorder level. Parts with stock/history cannot be deleted. There is no formal purchase order, supplier payable or stock return workflow in this version.

## Services

Use Service catalog to add common labor services with name, description, price and expected duration. Catalog duration is informational; appointments are scheduled explicitly. Existing estimate items retain their recorded descriptions/prices when the catalog changes.

## Reports

Set From and To dates to view collections and invoices in that date range. Collection totals use payment receipt time. Invoiced totals use issue time. Receivables and overdue balances cover all unpaid invoices; inventory value is current quantity × current recorded cost, not a historical accounting valuation. The pipeline counts all current order statuses. Export payments CSV downloads period receipts for reconciliation. These are operational reports, not a general ledger or a tax filing.

## Business settings and staff

Administrators can change the business name, tagline, address, contact details, logo, tax rate and invoice footer. Supported currencies: PHP, USD, SGD, EUR, GBP, AUD, CAD and MYR. One installation uses one currency with two decimal places. Currency is locked when services, parts or work orders exist. Choose the correct currency before real setup. Upload a square PNG/JPEG/WebP logo up to 2 MB and 3000×3000 pixels, or restore the original sample mark. Uploaded images are re-encoded as PNG.

Create staff accounts using unique emails and strong temporary passwords. Roles are fixed for this MVP, and permissions are enforced on the server. Reset passwords or deactivate accounts from Team & access. Deactivation blocks further access and role/password changes revoke existing database sessions. An administrator cannot deactivate or demote their own account, and at least one active administrator is required.

| Role | Main responsibility |
|---|---|
| Administrator | All modules, staff and business settings |
| Manager | Workshop operations, inventory, suppliers, billing, reports, audit |
| Advisor | Reception, customers/vehicles, appointments, work orders; read-only stock, services and billing |
| Technician | Assigned work orders and findings/progress; read-only vehicles, services and stock |
| Cashier | Invoices, payments and reports; read-only customers, vehicles and orders |

## Errors and recovery

Validation messages appear at the top of the page and preserve most entered text; passwords and uploads must be re-entered. Correct the listed fields before submitting again. If the session expires, reload and sign in again. If access is denied, ask your administrator about your role or job assignment. For an unexpected error, report the page, approximate time and action to the administrator. Do not repeatedly resubmit a payment using a fresh form without checking payment history first.

Your administrator is responsible for backups, restore tests, updates and protection of customer information. Do not put card numbers, sensitive identity documents or passwords in notes fields.
