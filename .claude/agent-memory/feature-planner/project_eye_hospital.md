---
name: project_eye_hospital
description: Al-Nour Eye Hospital HMS architecture — modular Laravel structure, module map, key models/patterns
metadata:
  type: project
---

## Architecture
Not a standard single-app/Models Laravel app. Uses a **custom module system**: `Modules/{ModuleName}/` (PSR-4 autoloaded as `Modules\\` -> `Modules/` in composer.json), each module self-contained with its own `Controllers/, Models/, Services/, Repositories/(+Contracts/), Actions/, DTOs/, Http/Requests/, States/, Enums/, Database/Migrations/, Database/Seeders/, Routes/, Providers/`.

Modules present: Accounting, Admin, Booking, Clinic, Doctor, HR, Insurance, Inventory, Labs, Laser, Lasik, Reporting, Surgery. `app/` only holds truly cross-cutting things: `App\Models\User`, `App\Enums\Department`, `App\Enums\EyeSide`, `App\DTOs\PatientDTO`, `App\Services\ActivityLogService`, `App\Services\AlertService`, `App\Repositories\BaseRepository`.

Root `database/migrations/` holds the original/base schema (bookings, doctors, services, surgeries, purchase_invoices, inventory, stock_permits, etc.) — later per-module migrations may live in `Modules/{X}/Database/Migrations/` (currently mostly empty; new module migrations should probably go there going forward, following whatever the most recent precedent is — check before assuming).

## No Patient model / no patients table
There is **no `patients` table and no `Patient` Eloquent model**. Patient identity/demographics (`patient_name`, `patient_phone`, `patient_age`, `national_id`, `gender`) live directly as columns on the `bookings` table (one row per visit/booking; `file_no` is the MRN that groups a patient's bookings — see `BookingController::patientFile()`). `App\DTOs\PatientDTO` is a small read DTO built from those booking columns, not a persisted entity. Any "add a patient field" request means: add a column to `bookings` (Modules/Booking), not a new table.

## Booking module (Modules/Booking)
- `Booking` model uses `HasUlids`, `InteractsWithMedia`, and `HasStates` (spatie/laravel-model-states) for `status`.
- `BookingStatus` (Modules/Booking/States) is an abstract State class, not a plain enum. Concrete states: WaitingState, ConfirmedState, InProgressState, CompletedState, CancelledState (each own file, `label()` in Arabic). Transitions are explicitly whitelisted in `BookingStatus::config()`. Adding a new terminal status (e.g. "Completed - Electronic") means adding a new State class + registering it in STATE_CLASSES/STATUS_LABELS/allowTransition.
- Per-status **visibility toggle** exists via Settings: `BookingStatus::settingKey()/isVisible()/visibleStatusNames()`, keyed `booking_status_visible_{status}`, admin-configurable, defaults true.
- `BookingRepository::filterAndPaginate()` already **hides `completed` and `cancelled` from the default booking list** unless the user explicitly filters by that status (`whereNotIn('status', ['completed','cancelled'])` when no status filter set). This is the exact mechanism to reuse for "surgery-completed bookings disappear from booking screen."
- `BookingController::update()` currently **hard-blocks editing when `$booking->status instanceof CompletedState`** (returns validation error, no permission check at all) — this is the code path to change for "allow editing completed bookings with a permission."
- `UpdateBookingStatusAction`: booking→Completed/Cancelled pushes status to the linked Surgery via `SurgeryService::updateStatusByBooking()`, and Completed+Paid triggers `AutoPostBookingPaymentAction` (accounting). This is a one-directional (booking→surgery) sync today; the reverse direction (surgery→booking) does not exist yet.
- Permissions on booking routes use Spatie `can:` **route middleware only** — `booking.view/create/edit/delete/pay`. **No Policy classes exist anywhere in the app** (grepped, zero `*Policy.php` files) — authorization is 100% via `Spatie\Permission` gate checks (`can:` middleware / `$user->can()`), not Laravel Policies. Follow that convention, don't introduce Policy classes unless asked.

## Surgery module (Modules/Surgery)
- `Surgery` model: `HasUlids` + `HasStates` for `status` (SurgeryStatus: Scheduled→Prep→InProgress→Completed, any active→Cancelled). belongsTo Booking (`booking_id`).
- `UpdateSurgeryStatusAction` (surgery/operations side) transitions surgery status and frees the OR bed on completion, but **does NOT currently notify/update the linked Booking at all**. This is the gap for "when surgery finishes on the ops side, booking should flip to Completed-Electronic and vanish from the booking screen" — needs new logic here (or a listener) to push booking status forward, mirroring the existing reverse-direction sync in `UpdateBookingStatusAction` but avoiding an infinite loop (different state names, so `instanceof CompletedState` checks won't double-fire — but accounting auto-post behavior for the new state needs an explicit decision).
- `SurgeryService::getOrRoomsWithBedStatus()` deliberately keeps a same-day completed case visible on the **OR beds board** (different screen than the booking list) — don't confuse the two "disappear/stay visible" behaviors.

## Inventory / Purchase Invoice module (Modules/Inventory)
- `InventoryItem` model (table `inventory`) already has `unit_cost` (purchase price), `sell_price`, `expiry_date`, `code`, `name` — everything needed for a type-ahead/autocomplete lookup already exists on the table; no schema change needed for the autocomplete feature itself.
- `PurchaseInvoice` / `PurchaseInvoiceItem`: **only `index` and `store` routes exist today — there is no update/destroy route or controller method at all** for purchase invoices. "Restrict edit/delete" is really "add edit/delete capability, gated tightly" — net new, not a tightening of something existing.
- `StockPermit` ("إذن صرف" = dispense permit / "إذن إضافة" = add permit) is a **separate, unrelated concept** — it governs moving inventory quantities in/out of stock (department-scoped issue vouchers), not authorization to edit/delete a purchase invoice. Don't conflate the two; treat the user's "OR gate it via a stock-permit-style release authorization" option as a bigger, more speculative design than a plain new Spatie permission (`purchases.edit`/`purchases.delete`) restricted to admin/supervisor roles — flag this as needing explicit user choice.
- Roles seeded in `database/seeders/RolesPermissionsSeeder.php`: `admin, doctor, reception, accountant, nurse, store_keeper`. There is **no "manager"/"supervisor" role today** — if the feature requires manager/supervisor-level gating distinct from admin, that role needs to be created (or the permission just added to an existing role), and this is a decision point for the user.

## Clinic module (Modules/Clinic)
- `ClinicSheet` model already has a `referral_to` free-form field, and there's an **existing but unwired `ReferPatientAction`** (Modules/Clinic/Actions/ReferPatientAction.php) that sets `referral_to` on the clinic sheet and can optionally auto-create a follow-up Booking in the target department. It is not referenced anywhere else (grepped) — no route/controller calls it currently. This is a strong existing building block for any "patient routing/triage" feature on the clinic screen — reuse/wire it up rather than building routing from scratch.
- `clinic/Index.vue` is currently a flat queue table (DataTable component) of today's clinic bookings, no routing/triage board yet.

## Conventions confirmed
- Enums: plain PHP backed enums with TitleCase case names and a `label()` method returning Arabic text (see `App\Enums\Department`, `App\Enums\EyeSide`, `Modules\Inventory\Enums\InvoiceStatus`, `Modules\Inventory\Enums\PermitType`). Model *status* fields that need transition rules use `spatie/laravel-model-states` (`HasStates`) instead of plain enums — mirror whichever pattern the field already uses.
- No Eloquent API Resources found in these modules — controllers pass Eloquent models/collections straight into `Inertia::render()` props.
- Frontend: Inertia v3 + Vue 3, pages under `resources/js/pages/{module}/`, shared components in `resources/js/components/shared/` (e.g. `DataTable.vue`, `Modal.vue`, `Badge.vue`, `SearchBar.vue`) — reuse these rather than building new list/modal primitives.
- Tests: `tests/Feature/{Module}/...Test.php`, one dir per module, PHPUnit classes (not Pest).
