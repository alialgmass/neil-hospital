# Feature Specification: Automatic Doctor Entitlements for Insurance & Contract Bookings

**Feature Branch**: `004-doctor-insurance-entitlements`
**Created**: 2026-09-07
**Status**: Draft — ready for agent
**Input**: User description: automatically create a doctor entitlement (مستحق طبيب) whenever a booking is created with نوع التعامل = شركة تأمين or تعاقد, valued at the doctor's own fee for that specific service.

---

## Problem Statement

When a receptionist creates a booking whose نوع التعامل (deal type) is **شركة تأمين (insurance)** or **تعاقد (contract)**, the hospital is paid later by the insurer/contracting party — there is usually **no cash payment event** recorded against the booking. Doctor entitlements (مستحقات الأطباء) currently accrue **only at payment time**, through `PayBookingController` → `DoctorClaimsService::computeShareForPayment` → `AutoPostDoctorDuesAction`. For insurance/contract bookings that payment event never fires, so:

- The doctor's due for that case is never recorded.
- The "مستحقات الأطباء" screen under-reports what the hospital owes its doctors.
- Accounting never accrues the doctor payable (Cr 2010) for insurance/contract work.
- Staff have to add the entitlement by hand, which is inconsistent, error-prone, and easy to forget or duplicate.

Separately, there is today **no place to record what a specific doctor charges for a specific service**. `doctors.dept_fees` is a per-department override and `services.dr_share` is a single flat number on the service, shared by every doctor. Neither can express "د/ عمر الجارحي charges 750 for مياه بيضاء while another surgeon charges a different amount".

## Solution

From the user's perspective:

1. **Services screen** gains an "أتعاب الطبيب (افتراضي)" field — a default doctor fee for the service, used as a fallback.
2. **Doctors screen** gains a "خدمات الطبيب" section: for each doctor, a list of `الخدمة + أتعاب الطبيب لهذه الخدمة`. This `Doctor + Service + Fee` record is the authoritative source of a doctor's fee for a service.
3. **Booking creation**: when a booking is saved with نوع التعامل = **شركة تأمين** or **تعاقد**, the system automatically creates a **doctor entitlement** linked to that booking, valued at **the doctor's fee for that specific service** (not the service price). No manual step.
4. Choosing an **insurance company remains optional** — a contract booking, or an insurance-typed booking where the company is filled in later, still produces the entitlement. Insurance-company data stays independent of the doctor-fee data.
5. The entitlement appears in the existing **مستحقات الأطباء** screen alongside the amounts that are computed from cash payments, and each booking is counted exactly once.
6. **Editing** the booking's doctor or service recalculates the entitlement in place. **Switching the deal type away** from insurance/contract removes the pending entitlement. **Re-saving** the booking any number of times never creates a second entitlement. **Cancelling/deleting** the booking voids/removes its pending entitlement.
7. **Normal (cash/card/transfer) bookings are completely unchanged** — no entitlement row is created for them, and their doctor dues keep accruing at payment time exactly as they do now.

---

## User Stories

### Configuration — Service default fee

1. As a clinic administrator, I want an "أتعاب الطبيب (افتراضي)" field on the service create form, so that I can set a baseline doctor fee for a service.
2. As a clinic administrator, I want that same field on the service edit form, so that I can adjust the baseline later.
3. As a clinic administrator, I want the service default fee to be optional and to default to 0, so that services without a configured doctor fee are still valid.
4. As a clinic administrator, I want the service default fee to accept only non-negative numbers, so that I cannot save invalid data.
5. As a clinic administrator, I want the existing `services.dr_share` value to be what this field reads and writes, so that no data is lost and existing insurance-surgery logic keeps working.

### Configuration — Per-doctor per-service fee

6. As a clinic administrator, I want a "خدمات الطبيب" section on the doctor create/edit screen, so that I can record which services a doctor provides.
7. As a clinic administrator, I want each row in that section to capture a service and the doctor's fee for that service, so that `Doctor + Service + Fee` is stored explicitly.
8. As a clinic administrator, I want to add multiple service-fee rows to one doctor, so that a surgeon who does several procedures is fully described.
9. As a clinic administrator, I want to edit a doctor's fee for a service, so that price changes are reflected in future bookings.
10. As a clinic administrator, I want to remove a service from a doctor, so that discontinued procedures stop appearing.
11. As a clinic administrator, I want each doctor to have at most one fee per service, so that lookups are unambiguous.
12. As a clinic administrator, I want the per-doctor fee to accept only non-negative numbers, so that I cannot save invalid data.
13. As a clinic administrator, I want existing doctors with no configured service fees to keep working, so that the feature can be rolled out gradually.

### Booking creation — entitlement generation

14. As a receptionist, I want the booking form to let me choose نوع التعامل including a new **تعاقد** option, so that contract cases are distinguishable from cash and insurance.
15. As a receptionist, when I create a booking with نوع التعامل = شركة تأمين, I want a doctor entitlement to be created automatically for the assigned doctor, so that I do not have to add it by hand.
16. As a receptionist, when I create a booking with نوع التعامل = تعاقد, I want the same automatic entitlement, so that contract work is captured identically.
17. As a receptionist, I want the entitlement amount to equal the assigned doctor's fee for the booking's service, so that "مياه بيضاء + د/ عمر الجارحي" produces 750, not the 5000 service price.
18. As a receptionist, when the doctor has no configured fee for that service, I want the system to fall back to the service's default doctor fee, so that a reasonable amount is still recorded.
19. As a receptionist, when neither a per-doctor fee nor a service default fee exists (both resolve to 0), I want **no** entitlement to be created and a non-blocking notice that the booking was saved without a doctor entitlement, so that the booking still succeeds and staff know to configure the fee.
20. As a receptionist, when I create a booking with نوع التعامل = شركة تأمين but leave the insurance company blank, I want the entitlement to still be created, so that company selection stays optional.
21. As a receptionist, when I create a **normal** booking (cash/card/transfer), I want **no** insurance/contract entitlement created, so that existing behaviour is preserved.
22. As a receptionist, when I create an insurance/contract booking with **no doctor assigned**, I want no entitlement created (nothing to attribute it to) and the booking to save normally.
23. As a receptionist, when I create an insurance/contract booking with no service selected, I want no entitlement created and the booking to save normally.
24. As a receptionist, I want the entitlement to be linked to the specific booking, so that reports can trace it back to the patient and file number.
25. As a receptionist, I want the entitlement to record the doctor, the service, the amount, and the source (insurance vs contract), so that the مستحقات screen can show a full breakdown.

### Booking editing — entitlement lifecycle

26. As a receptionist, when I open an insurance/contract booking that already has an entitlement and save it again without changing the doctor or service, I want no second entitlement to be created, so that saving is safe to repeat.
27. As a receptionist, when I change the **doctor** on an insurance/contract booking, I want the existing entitlement re-pointed to the new doctor and its amount recalculated from the new doctor's fee for the service, so that no stale entitlement is left against the old doctor.
28. As a receptionist, when I change the **service** on an insurance/contract booking, I want the entitlement amount recalculated from the doctor's fee for the new service, so that the amount stays correct.
29. As a receptionist, when I change نوع التعامل on a booking **from** insurance/contract **to** a normal method, I want the pending entitlement removed, so that the hospital does not owe a doctor for a case that became a cash case.
30. As a receptionist, when I change نوع التعامل **to** insurance/contract on a booking that previously had none, I want an entitlement created on save, so that the edit path matches the create path.
31. As a receptionist, when I edit an insurance/contract booking whose entitlement has **already been settled/paid** to the doctor, I want the settled entitlement left untouched and a notice shown, so that historical payouts are not silently rewritten.
32. As a receptionist, when I edit a booking many times, I want at most one live entitlement per booking at all times, so that reports never double-count.
33. As an administrator, when I cancel an insurance/contract booking, I want its pending entitlement voided, so that cancelled cases stop inflating doctor dues.
34. As an administrator, when I delete an insurance/contract booking, I want its entitlement removed along with it, so that no orphan entitlement survives.
35. As an administrator, when I cancel/delete a booking whose entitlement was already settled, I want the settled record preserved (voided-but-retained), so that the audit trail and the doctor payout history stay intact.

### Reporting & accounting

36. As an accountant, I want each insurance/contract entitlement to post a doctor-payable accrual (Dr 5110/5120 / Cr 2010) when it is created, so that the doctor payable on the balance sheet reflects insurance/contract work.
37. As an accountant, I want re-calculation of an entitlement to reverse the prior accrual and post the corrected amount, so that the ledger matches the live entitlement.
38. As an accountant, I want voiding an entitlement (deal type changed, booking cancelled/deleted) to reverse its accrual, so that the payable is released.
39. As an accountant, I want entitlement accrual to be idempotent per booking, so that repeated saves never post duplicate journal entries.
40. As a finance manager, I want the "مستحقات الأطباء" screen to include persisted insurance/contract entitlements together with the cash-payment-derived shares, so that the net due per doctor is complete.
41. As a finance manager, I want each booking to contribute to a doctor's total from **exactly one** source — the persisted entitlement for insurance/contract bookings, the computed share for everything else — so that totals are never doubled.
42. As a finance manager, I want the Pentacam department excluded from entitlement accounting exactly as it is excluded today, so that department rules stay consistent.
43. As a finance manager, I want existing doctor-claims reports (`DoctorClaimsReportController`, `ClaimCalculator`, monthly settlement) to keep working, adjusted only so they do not double-count insurance/contract bookings.

### Data integrity & rollout

44. As a developer, I want a unique constraint on entitlement `booking_id`, so that duplicates are impossible at the database level.
45. As a developer, I want the entitlement sync logic to live in one domain action reused by the create, update, and cancel/delete booking flows, so that the rule is defined once.
46. As a developer, I want the new `Contract` deal type added to the `PayMethod` enum with an Arabic label, so that string comparisons stay enum-based per project convention.
47. As a developer, I want historical bookings to be left without entitlements unless a separate backfill task is run, so that this change does not retroactively mutate closed accounting periods.

---

## Implementation Decisions

### Deal type
- Add `Contract = 'contract'` case to `Modules\Booking\Enums\PayMethod`, label `تعاقد`. `نوع التعامل` on the booking form maps to `pay_method`. "Insurance/contract booking" is defined as `pay_method ∈ {PayMethod::Insurance, PayMethod::Contract}`.
- Update every `in:cash,card,transfer,insurance` validation rule and `PayMethod` match/label site to include `contract` (`StoreBookingRequest`, `UpdateBookingRequest`, `PayBookingController`, booking form Vue, any PayMethod switch).
- Insurance company stays optional in all cases. `ins_company_id` remains `nullable`. `StoreBookingRequest`'s existing `service_id => required_with:ins_company_id` rule is unchanged; no new `required_if` on company is added.

### Per-doctor per-service fee
- New pivot table `doctor_service`: `doctor_id` (FK, ULID), `service_id` (FK, ULID), `fee` (decimal 10,2, default 0), timestamps, unique(`doctor_id`,`service_id`).
- `Doctor` gains a `services()` `belongsToMany` relation with `withPivot('fee')`; `Service` gains the inverse if convenient.
- `Doctor` gains `feeForService(Service|string $service): ?float` returning the pivot fee when a row exists, else `null`.
- `StoreDoctorRequest` / `UpdateDoctorRequest` accept `services` as an array of `{service_id, fee}`; `CreateDoctorAction` / `UpdateDoctorAction` sync the pivot (full replace on update). `DoctorController::destroy` guard is unaffected (pivot rows are not "payments or shifts").
- Doctors `Index.vue` create/edit modal gains a repeatable "خدمات الطبيب" sub-form mirroring the existing `dept_fees` UI pattern.

### Service default fee
- Reuse the existing `services.dr_share` column — no migration. Surface it in `admin/Services.vue` create/edit as "أتعاب الطبيب (افتراضي)" and add `dr_share => ['nullable','numeric','min:0']` to `StoreServiceRequest` / `UpdateServiceRequest`, wired through `CreateServiceAction` / `UpdateServiceAction`.

### Fee resolution (single source of truth)
Order, first non-null wins:
1. `Doctor::feeForService(service)` — the `doctor_service` pivot fee.
2. `service.dr_share` — the service default doctor fee.
3. `null` → no entitlement created; booking still succeeds with a non-blocking flash notice.

The service **price** (`price` / `one_eye_price` / `both_eyes_price` / `ins_price`) is never used for the entitlement amount.

### Doctor entitlement store
- New table `doctor_entitlements`: `id` (ULID), `booking_id` (FK, **unique**), `doctor_id` (FK), `service_id` (FK, nullable), `amount` (decimal 10,2), `source` (enum `insurance`|`contract`), `status` (enum `pending`|`settled`|`void`, default `pending`), `settled_at` (nullable), `dr_payment_id` (nullable FK to `dr_payments`), timestamps.
- New model `Modules\Doctor\Models\DoctorEntitlement` with `booking()`, `doctor()`, `service()` relations; new enums `EntitlementSource`, `EntitlementStatus` (TitleCase keys).
- `Booking` gains `doctorEntitlement(): HasOne`.
- `status` transitions: `pending → settled` (when included in a `DoctorPayment` payout), `pending → void` (deal type changed away, booking cancelled/deleted). `settled` is terminal and immutable via this feature.

### Domain action — the one seam for the rule
- New `Modules\Doctor\Actions\SyncDoctorEntitlementAction` (domain logic, no HTTP). Single entry point: `execute(Booking $booking): void`. It is idempotent and decides create / recalculate / void based on current booking state vs. the existing entitlement:
  - Not insurance/contract, or no doctor, or fee resolves to null → ensure no `pending` entitlement (void an existing pending one).
  - Insurance/contract + doctor + fee → upsert the `pending` entitlement keyed by `booking_id` (`updateOrCreate`), setting `doctor_id`, `service_id`, `amount`, `source`.
  - Existing entitlement is `settled` → no-op, surface a caller-readable signal (return value or exception the callers translate to a flash notice).
- Wired into:
  - `CreateBookingAction::execute` — after the booking (and surgery/claim) is created.
  - `UpdateBookingAction::execute` — after `bookingService->update`, alongside `syncInsuranceClaim` (same "sync on edit" shape).
  - `CancelBookingAction::execute` — void the pending entitlement after status flips to cancelled.
  - Booking deletion path (`BookingController::destroy` / repository) — DB-level `cascadeOnDelete` on `booking_id` handles hard delete; the action is still called to reverse accounting for pending entitlements before delete.
- Runs inside the same DB transaction as the booking write where the surrounding action already opens one; otherwise wrap the booking write + sync together.

### Accounting integration
- On entitlement create/recalculate/void, call `AutoPostDoctorDuesAction` with `idempotencyKey = "doctor_entitlement:{booking_id}"` and `reference = booking.file_no`.
- Recalculation and voiding use the existing journal reversal mechanism (per `JournalService` idempotency/reversal constraints) keyed on the same key, then re-post the new amount. Pentacam is skipped by `AutoPostDoctorDuesAction` already.
- This mirrors the existing `PayBookingController` accrual; the payment-time path for cash bookings is untouched.

### مستحقات الأطباء screen (no double counting)
- `DoctorClaimsService::calculateClaims` and `summarizeAll` change so that, per booking:
  - `pay_method ∈ {insurance, contract}` → take the persisted `doctor_entitlements` row (amount + status), **skip** `computeDrShare` for that booking.
  - otherwise → unchanged computed share.
- `net_due` = (Σ pending/settled entitlements + Σ computed shares) − Σ `dr_payments`.
- `ClaimCalculator` (used by `DoctorService::getActiveDoctorsWithClaims` and monthly settlement) gets the same "skip insurance/contract bookings, add their entitlements" adjustment, or is pointed at the shared logic.

### Modules touched
- `Modules/Booking`: `PayMethod` enum, `BookingData` DTO (no shape change; `pay_method` already carried), `StoreBookingRequest`, `UpdateBookingRequest`, `CreateBookingAction`, `UpdateBookingAction`, `CancelBookingAction`, `BookingController::destroy`, `Booking` model relation.
- `Modules/Doctor`: new `doctor_service` + `doctor_entitlements` migrations, `DoctorEntitlement` model + 2 enums, `Doctor` model (`services()`, `feeForService()`), `StoreDoctorRequest`/`UpdateDoctorRequest`, `CreateDoctorAction`/`UpdateDoctorAction`, `SyncDoctorEntitlementAction`, `DoctorClaimsService`, `ClaimCalculator`.
- `Modules/Admin`: `StoreServiceRequest`/`UpdateServiceRequest`, `CreateServiceAction`/`UpdateServiceAction`.
- `Modules/Accounting`: reuse `AutoPostDoctorDuesAction` (no change expected) / `JournalService` reversal.
- Frontend: `resources/js/pages/admin/Services.vue`, `resources/js/pages/doctors/Index.vue`, booking form component (deal-type option + `contract`), Wayfinder regen.
- Seeders/factories: `DoctorFactory` state for service fees, `BookingFactory` insurance/contract states, new `DoctorEntitlementFactory`.

### API / contract notes
- No new HTTP endpoints. Doctor service-fees and service default fee ride on the existing `doctors.store/update` and `admin.services.store/update` payloads.
- Booking store/update payloads gain `pay_method: 'contract'` as an accepted value; response/redirect shape unchanged (`back()->with(...)`).

---

## Testing Decisions

**What makes a good test here**: assert observable outcomes — a `doctor_entitlements` row exists / doesn't / has amount N / has status void; the مستحقات screen totals; a journal entry was posted with the expected key and amount; the booking still saved and the flash message is present. Do **not** assert on private methods of `SyncDoctorEntitlementAction`, internal query counts, or the exact wording of internal variables.

**Primary seam — booking HTTP endpoints (feature tests, full stack):**
- `POST /booking` (store), `PUT/PATCH /booking/{id}` (update), the cancel route, `DELETE /booking/{id}`.
- Prior art: `tests/Feature/Booking/DestroyBookingTest.php`, `UpdateBookingInsuranceClaimTest.php`, `UpdateBookingTest.php`, `PayBookingTest.php`, `PayBookingAccountingTest.php`, `tests/Feature/Doctor/DoctorDepartmentsTest.php`, `tests/Feature/DoctorDeptFeesTest.php`.
- Scenarios:
  1. Normal cash booking → no `doctor_entitlements` row.
  2. Insurance booking, doctor has pivot fee 750 for the service → one entitlement, `amount = 750`, `source = insurance`, linked to the booking.
  3. Contract booking → one entitlement, `source = contract`.
  4. "مياه بيضاء" service price 5000, doctor fee 750 → entitlement amount is 750, not 5000.
  5. Insurance booking, no pivot fee, service `dr_share = 600` → entitlement amount 600 (fallback).
  6. Insurance booking, no pivot fee and `dr_share = 0` → no entitlement, booking saved, flash notice present.
  7. Insurance booking with `ins_company_id` omitted → entitlement still created.
  8. Insurance booking, no doctor → no entitlement, booking saved.
  9. Update: change doctor → entitlement re-pointed, amount recalculated, still exactly one row.
  10. Update: change service → amount recalculated.
  11. Update the same booking twice with no doctor/service change → still exactly one row (idempotency).
  12. Update: `pay_method` insurance → cash → pending entitlement removed/voided.
  13. Update: `pay_method` cash → contract → entitlement created.
  14. Update a booking whose entitlement is `settled` → entitlement unchanged, notice shown.
  15. Cancel an insurance booking → entitlement `void`.
  16. Delete an insurance booking → entitlement gone; settled entitlement retained as `void`.
  17. Accounting: creating an insurance entitlement posts Dr 5110/5120 / Cr 2010 once; a second save posts nothing new (idempotency key `doctor_entitlement:{booking_id}`).
  18. مستحقات الأطباء screen: a doctor with one insurance booking (entitlement 750) and one cash booking (computed share 300) shows total 1050, with the insurance booking counted once.

**Secondary seam — `SyncDoctorEntitlementAction` unit tests:**
- Fee-resolution matrix (pivot present / pivot absent + `dr_share` / both zero) and the create/recalculate/void decision table, driven by `Booking` + `Doctor` + `Service` factory rows.
- Prior art: `tests/Feature/DoctorDeptFeesTest.php`, `tests/Feature/Booking/ServicePricingTest.php`.

**Configuration seams (feature tests on existing endpoints):**
- `POST /doctors` / `PUT /doctors/{id}` with `services[]` → `doctor_service` rows synced; unique(`doctor_id`,`service_id`) enforced; removing a row deletes the pivot.
- `POST /admin/services` / update with `dr_share` → persisted and echoed. Prior art: `tests/Feature/ServiceRevenueAccountTest.php`.

**Regression:**
- Existing `PayBookingTest`, `PayBookingAccountingTest`, `ReverseBookingPaymentTest`, `DoctorPaymentAccountingTest`, `UpdateBookingInsuranceClaimTest`, `ClaimCalculator`-backed tests must stay green — run the `Booking` and `Doctor` feature directories plus `--filter=Claim` after the change, then the full suite.

Run with `php artisan test --compact` and a path/`--filter` per the project testing rules.

---

## Out of Scope

- Changing how **cash/card/transfer** bookings accrue doctor dues — the payment-time path (`PayBookingController` → `computeShareForPayment` → `AutoPostDoctorDuesAction`) is unchanged.
- Redesigning the five existing fee strategies in `DoctorClaimsService` / `computeDrShare` — only the "skip insurance/contract bookings, read the persisted entitlement instead" adjustment is in scope.
- The insurance **claim** workflow (`InsuranceClaim`, states, approvals, payouts) — unchanged; entitlements are independent of claims.
- The doctor **payout** flow (`DoctorPayment` / `dr_payments`, `RecordDoctorPaymentAction`) beyond marking entitlements `settled` when a payout covers them (settlement linkage can be a follow-up if it grows large).
- **Retroactive backfill** of entitlements for bookings created before this feature ships — a separate, explicitly-run artisan/seeder task, guarded against closed accounting periods.
- Per-eye or per-quantity doctor fees, doctor fee history/versioning, multi-currency, tax on doctor fees.
- Any UI for browsing/editing raw `doctor_entitlements` rows beyond their appearance in the existing مستحقات الأطباء screen.

---

## Further Notes

- **Domain glossary**: نوع التعامل = `pay_method`; شركة تأمين = `PayMethod::Insurance`; تعاقد = `PayMethod::Contract` (new); مستحق طبيب = `DoctorEntitlement`; أتعاب الطبيب لخدمة = `doctor_service.fee`; أتعاب الطبيب الافتراضية = `services.dr_share`; مستحقات الأطباء screen = `doctors/Claims` (Inertia); مدفوعات الأطباء = `dr_payments`.
- **Why a persisted entitlement rather than extending the computed `DoctorClaimsService`**: insurance/contract bookings have no payment event to hang a computation on, the amount must be frozen at booking time (the doctor's fee can change later), and the accounting accrual needs a concrete, reversible record.
- **Idempotency** is enforced three ways: unique `doctor_entitlements.booking_id`, `updateOrCreate` in `SyncDoctorEntitlementAction`, and the `doctor_entitlement:{booking_id}` journal idempotency key.
- The existing `AutoPostDoctorDuesAction` already guards `amount <= 0` and `Department::Pentacam`; the entitlement path inherits both.
- `services.dr_share` is currently also read by `DoctorClaimsService::insuranceSurgeryFixedFee` / `computeInsuranceSurgeryShare` for insurance surgery. Reusing it as the service-level default doctor fee is consistent with that existing meaning; confirm the surgery insurance path still resolves correctly once those bookings also produce a persisted entitlement (scenario 18 covers double-count; add a surgery-specific variant).
- **Issue tracker**: no issue tracker / triage-label vocabulary is configured for this project, so this spec is filed as `specs/004-doctor-insurance-entitlements/spec.md` following the existing spec-kit convention (`001-eye-hospital-hms`, `002-fix-booking-flow`, `003-accounting-guide-v2`). Run `/setup-matt-pocock-skills` to wire up a real tracker if you want `to-spec` to publish issues directly and apply the `ready-for-agent` label.
