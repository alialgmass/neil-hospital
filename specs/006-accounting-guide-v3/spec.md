# Accounting Guide v3 — Alignment with Al-Nour Hospital Accounting Manual

## Problem Statement

The hospital's accounting/pricing/booking engine (`Modules/Accounting`, `Modules/Doctor`, `Modules/Insurance`, `Modules/Booking`, `Modules/Surgery`) was built against an earlier draft of the hospital's accounting manual. A newer, more precise extract of that manual ("مستخلص المنطق البرمجي — دليل مستشفى النور", Aug 2026 v2.0) fixes several rules the current implementation gets wrong or leaves unimplemented:

- Insurance claim revenue always posts to one account (4110) regardless of department, so the income statement cannot show revenue by department for insurance work.
- Insurance receivables always post to one aggregate account (1030) regardless of which of the 17 contracted insurance companies is involved, so there is no per-company receivable balance.
- Insurance doctor fees are posted through the same accrual path as cash doctor fees (ending up in the doctor-payable account, 2010), when the manual requires insurance doctor fees to be paid out in cash immediately and never touch 2010.
- Insurance claims never post supply/inventory consumption, so surgical/lasik supplies used on insurance cases are not costed.
- Insurance collection never accounts for Egyptian withholding tax ("خصم وإضافة"), so the recoverable withholding asset (1080) is never recorded and collected cash is booked without the correct split against bank/receivable.
- Partial claim approval (approved amount less than the submitted claim) has no explicit accounting treatment tied to the approval step itself — only a shortfall write-off at collection time.
- The Laser department has no "fixed hospital revenue" concept — the manual requires Laser's hospital share to be a fixed amount per service, with the doctor absorbing the remainder (and any patient discount), which today's schema cannot express.
- There is no monthly depreciation posting, and no structure for allocating shared/administrative expenses across cost centers.
- Two parallel doctor-fee calculators (`DoctorClaimsService` and `ClaimCalculator`) can disagree on the same doctor's dues for the same period, which risks the hospital paying/reporting the wrong amount to a doctor.

Because this is a live financial ledger, any mismatch between the code and the manual either mis-states revenue/expense by department, mis-states a doctor's or an insurance company's balance, or breaks the accounting identity (total debits = total credits) for a scenario the manual describes.

## Solution

Re-align the existing accounting/booking/doctor-fee/insurance engine with the manual, changing only the behavior the manual requires, while keeping the existing chart of accounts (`AccountCode` enum — already numerically correct), the existing `cost_center` column/`CostCenter` enum on `journal_entries`, the existing `JournalService` idempotency/reversal mechanism, and the existing Action-based (no observers, no jobs) posting architecture.

Concretely: route insurance revenue and receivables by department/company instead of a single hardcoded account each; add a dedicated cash-immediate insurance-doctor-fee posting path that never touches 2010; post supply consumption for insurance cases (never reversed on rejection); add withholding-tax handling at collection using a per-company withholding percentage; treat the existing `approved`/`approved_amount` fields as the partial-approval mechanism instead of inventing a new claim status; add a fixed-hospital-revenue field for Laser services so the doctor's cut is computed as a remainder; add monthly depreciation posting; add an (unpopulated) allocation-rule structure for shared expenses; and switch the surgery/lasik doctor-supply-charge posting from the current contra-expense model (5115) to the revenue model (4070) the manual specifies as final.

## User Stories

1. As an accountant, I want insurance claim revenue posted to the correct department's insurance revenue account (4110 Clinic, 4120 Lab, 4130 Surgery, 4140 Lasik, 4150 Laser) instead of always 4110, so that the income statement shows correct revenue by department for insurance work.
2. As an accountant, I want each insurance claim's receivable posted to that company's own sub-account (1031–1047) instead of the shared 1030, so that I can see how much each insurance company individually owes the hospital.
3. As an accountant, I want to keep being able to see the aggregate insurance receivable (1030) as a roll-up for reporting, without it being the account any individual claim posts to.
4. As an accountant, I want insurance revenue by department, insurance revenue by company, and revenue by department × company to be extractable from the existing data (via the company dimension on the receivable/claim), without adding a receivable-per-company × per-department explosion of accounts.
5. As the hospital owner, I want a fixed 50 EGP (or whatever `services.dev_treasury_fee` says) moved to the development treasury (1011) on every cash service, without that 50 EGP reducing the recorded revenue amount — this already works and must keep working unchanged.
6. As the hospital owner, I want a doctor's cash-department fee (Clinic/Lab) computed on the amount actually paid minus the 50 EGP development fee, using each doctor's own configured percentage (not a hardcoded 50%/27%/25%) — this already works and must keep working unchanged.
7. As the hospital owner, I want Laser services to have a fixed hospital-revenue amount configured per service, with the doctor's fee computed as `(paid − development fee) − fixed hospital revenue`, so that the hospital's take from Laser is guaranteed regardless of what's left for the doctor.
8. As the hospital owner, I want a patient discount on a Laser service to reduce the doctor's fee first, not the fixed hospital revenue, unless a future rule says otherwise.
9. As an accountant, I want Pentacam services to never generate a doctor-fee journal entry (5110/5120/5130), which already works and must keep working unchanged.
10. As an accountant, I want Surgery-cash and Lasik doctor fees computed as `(paid − development fee) − supplies selling price`, with supplies posted at selling price against the doctor and at purchase price against inventory — this arithmetic already works and must keep working unchanged.
11. As an accountant, I want the journal entry that charges supplies to the doctor on Surgery/Lasik cases to credit 4070 (supplies-sale revenue) instead of 5115 (contra-expense), per the manual's final worked example, so that the hospital's supplies profit margin shows as revenue in the income statement rather than as an expense reduction.
12. As an accountant, I want the existing `5115`-based test coverage and any other code referencing that posting model updated to reflect the 4070 model, so there is no stale test asserting the old behavior.
13. As an accountant, I want insurance doctor fees posted as a fixed amount per case, debited to 5130 and credited directly to cash (1010), the moment the claim is submitted — never accruing to 2010 (doctor payable).
14. As an accountant, I want the generic doctor-dues accrual path (`AutoPostDoctorDuesAction`, which posts to 2010) to never fire for insurance-paid bookings, so insurance doctor fees cannot accidentally double-post or land in the wrong account.
15. As an accountant, I want supply/inventory consumption for an insurance surgical/lasik case posted (debit 5010/5020 cost account, credit 1051 inventory) at the time of service, matching how cash cases already post supply consumption.
16. As an accountant, I want a full claim rejection to reverse the claim's revenue and receivable entry, and to reverse the insurance doctor's fee entry if one was posted, but to never reverse the supply consumption entry, because the supplies were physically used.
17. As an accountant, I want a partially approved claim (`approved_amount < invoice/claim amount`) to be usable at collection time to compute the correct bank/withholding-tax/bad-debt split, using the existing `approved` status and `approved_amount` field rather than a new claim status.
18. As an accountant, I want claim collection to post: debit bank (1020) for the net amount actually received, debit withholding-tax-prepaid (1080, an asset) for the tax withheld, and credit the company's receivable (1031–1047) for the full original claim amount — with any gap between the original claim amount and the approved amount posted to bad debt (5300).
19. As an accountant, I want the withholding tax amount at collection derived from a withholding percentage configured per insurance company, so each company's actual contractual withholding rate is used rather than one global rate.
20. As an accountant, I want the existing full/partial-collection shortfall-to-bad-debt logic preserved for whatever gap withholding-based recalculation doesn't already explain, so no existing correctly-working collection scenario silently changes.
21. As a developer, I want a single reusable calculation for "doctor fee by department" (currently `DoctorClaimsService`) to be the only place this logic lives, and the second, diverging implementation (`ClaimCalculator`) to be removed or rewritten to delegate to the first, so the doctor-list dashboard and the claims report can never disagree on a doctor's dues.
22. As a developer, I want every new AutoPost action to follow the existing idempotency convention (a deterministic `idempotency_key` per source record, checked via `JournalService::record()`), so re-saving a booking/claim, a retried request, or a duplicate call never creates a duplicate journal entry.
23. As a developer, I want every new journal entry produced by booking/claim posting to carry the correct `cost_center` (from the existing `CostCenter` enum, department-derived, never user-chosen), consistent with how `AutoPostBookingPaymentAction` already resolves cost centers.
24. As the hospital owner, I want a monthly depreciation posting job/action that debits the correct depreciation expense account (5261–5265, by asset class) and credits the corresponding accumulated-depreciation contra-asset account, based on asset cost, useful life, and accumulated depreciation to date, without ever changing the asset's original cost.
25. As the hospital owner, I want a structure (table/config) for defining shared-expense allocation rules across cost centers (e.g., rent, admin salaries) to exist, ready for an administrator to fill in percentages later — without the system inventing or defaulting any percentage itself.
26. As a finance manager, I want to be able to query revenue, expenses, and net profit per cost center for a month, a year, or an arbitrary date range, and compare centers against each other, using the existing `cost_center` column and account `group` (revenue/expense) rather than a hardcoded list of account codes.
27. As a QA engineer, I want automated tests proving `total debits == total credits` for every new/changed posting scenario in this spec (Clinic, Lab, Laser, Pentacam, Surgery cash, Lasik, and all insurance stages), so a broken double-entry invariant is caught before release.
28. As a QA engineer, I want a test proving that running the same posting twice (same booking payment, same claim submission/collection/rejection) does not create a duplicate journal entry, for every action touched by this spec.
29. As an accountant, I want confirmation, via a test, that the 50 EGP development-fee transfer never reduces the revenue amount recorded against a booking, for any department, including Laser's new fixed-revenue path.
30. As an accountant, I want confirmation, via a test, that 1080 (withholding tax) is posted and classified as an asset (debit-nature), never as an expense.
31. As a developer, I want the `Service.dept` and `CostCenter`/`Department` enum sets to stay mutually consistent (e.g., Pentacam already valid everywhere it needs to be) wherever this spec's changes touch those code paths, without a broad unrelated enum audit.

## Implementation Decisions

### Chart of accounts / cost centers — no change needed
- The existing `Modules/Accounting/Enums/AccountCode.php` already contains every account code the manual requires (1010–5340), correctly classified by group (1080 withholding tax is already an Asset). No new account codes and no chart-of-accounts migration are needed.
- The existing `Modules/Accounting/Enums/CostCenter.php` already has all 8 required centers. Its Pentacam value stays `CC-PENTACAM` (not `CC-PENTA` as literally written in the manual) — this is a deliberate naming decision to avoid a data migration across existing `journal_entries.cost_center` history; any place this spec writes new code, use the existing `CostCenter::Pentacam` case.
- `journal_entries` already has `cost_center`, `idempotency_key` (unique), `reversal_of_id`, `reversed_at`. No schema change needed for those.

### 4070 vs 5115 (supplies charged to doctor) — switch to 4070
- `ProcessBundleSupplyAction::postBundleChargeEntry()` changes from `Dr 2010 (DOCTOR_PAYABLE) / Cr 5115 (SUPPLY_COST_RECOVERED_FROM_DOCTOR)` to `Dr 2010 (DOCTOR_PAYABLE) / Cr 4070 (SUPPLIES_SALE_REVENUE)`, at the bundle's selling price — same debit side, only the credit account changes.
- `AccountCode::SUPPLY_COST_RECOVERED_FROM_DOCTOR` (5115) stays defined in the enum (do not delete an account code with historical postings against it) but is no longer used by any posting action going forward.
- Historical journal entries already posted against 5115 are left as-is (no backfill/rewrite of historical data) — this only changes behavior for postings created after the change ships.

### Insurance revenue routing — by department
- `AutoPostInsuranceClaimAction::onSubmit()` resolves the credit account from the claim's department (via the claim's linked `Booking`/`Service`) using a department→account map: Clinic→4110, Lab→4120, Surgery→4130, Lasik→4140, Laser→4150. Reuse the same department-resolution approach `AccountCode::deptRevenueCode()`/`doctorExpenseCode()` already use for cash postings, rather than inventing a new lookup.
- `AccountCode::INSURANCE_REVENUE` (4110) remains Clinic's insurance revenue account specifically (no longer the catch-all default for every department).

### Insurance receivable routing — by company
- Add the 17 contracted-entity accounts (1031–1047) to the chart via a seeder/migration data addition (the account codes already exist as a documented range in the manual; they need to be created as actual `accounts` rows, one per company, under parent 1030).
- Add an account reference from `InsuranceCompany` to its receivable account (e.g., a `receivable_account_id` column on `insurance_companies`, or a resolvable code stored on the company) so `AutoPostInsuranceClaimAction` can look up the correct 1031–1047 account for a given claim's company instead of the hardcoded 1030.
- 1030 remains a non-postable parent/roll-up account (it already is), used for reporting aggregation only — no individual claim posts to it directly, consistent with `AccountCode::nonPostableCodes()`.
- Both `Modules/Booking/Models/InsuranceCompany.php` and `Modules/Insurance/Models/InsuranceCompany.php` (duplicate models on the same table) need the new column/relationship reflected consistently.

### Insurance doctor fee — new dedicated cash-immediate action
- New `AutoPostInsuranceDoctorCashPaymentAction`, fired at claim submission (alongside `AutoPostInsuranceClaimAction::onSubmit()`), posts `Dr 5130 (INSURANCE_DOCTOR_FEES) / Cr 1010 (CASH)` for the case's fixed doctor amount. Idempotency key pattern consistent with existing convention, e.g. `insurance_doctor_fee:{claim_id}`.
- The fixed per-case doctor amount is resolved the same way the existing insurance/contract fixed-fee path already resolves it (`doctor_service` pivot fee, falling back to `services.default_dr_fee`) — reuse, don't reinvent, that resolution logic.
- `SyncDoctorEntitlementAction` (and by extension `AutoPostDoctorDuesAction`) must be changed to skip insurance-paid bookings entirely — insurance doctor fees no longer flow through `doctor_entitlements`/2010 at all; the new action is the only poster for insurance doctor fees.
- On full claim rejection, if an insurance-doctor-fee entry was posted for that claim, `AutoPostInsuranceClaimAction::onReject()` (or a hook it calls) reverses it via the existing `JournalService::reverse()`.

### Insurance supplies consumption — new posting, never reversed
- Wire supply/inventory consumption posting (debit 5010/5020 cost-by-department account, credit 1051 inventory) into the insurance claim submission flow for claims tied to a surgery/lasik case with recorded supplies — reuse the existing per-item costing logic from `ProcessBundleSupplyAction`'s inventory-consumption half (not its doctor-charge half, which doesn't apply to insurance — insurance doctor fee is fixed, not supplies-adjusted).
- This posting is excluded from `AutoPostInsuranceClaimAction::onReject()`'s reversal — rejection only reverses the revenue/receivable entry (and the insurance-doctor-fee entry, per above), never the supplies entry.

### Insurance collection — withholding tax + bank
- Add a `withholding_pct` (or similarly named) configuration field on `insurance_companies`, administrator-set, no default value invented by the system.
- `AutoPostInsuranceClaimAction::onCollect()` is rewritten to: compute `withholding_amount = approved_amount(or claim amount if no partial approval) * company.withholding_pct`; post `Dr 1020 (BANK) [net] + Dr 1080 (WITHHOLDING_TAX_PREPAID) [withholding_amount] / Cr {company receivable account} [full original claim amount]`; if `approved_amount < claim amount`, post the shortfall to `Dr 5300 (BAD_DEBT)` as an additional debit leg in the same entry set, keeping the accounting identity balanced.
- Partial approval uses the existing `InsuranceClaim.approved_amount` field and the existing `approved` status — no new `ClaimStatus` state is added. `UpdateInsuranceClaimAction` must ensure `approved_amount` is actually set/validated when a claim transitions to `approved` (today nothing populates or checks it).
- The current cash-only (1010) collection path is replaced by this bank-based path per the manual; confirm with existing tests whether any real workflow needs a cash-collection option preserved — if so, keep it as an alternate branch, otherwise bank becomes the only collection account for insurance.

### Laser fixed hospital revenue
- Add a field to `services` (e.g. `laser_fixed_revenue`, nullable decimal) used only for Laser-department services.
- `DoctorClaimsService`'s generic `computeServiceShare()` path is extended (or a new branch added) so that when a service has `laser_fixed_revenue` set, the doctor's fee is computed as `(paid − development fee) − laser_fixed_revenue` instead of the percentage/fixed formula, and any patient discount reduces the doctor's fee (not the fixed revenue) by flowing through the same `paid` amount that already reflects the discount.
- Cost-center/revenue-account posting for Laser cash bookings is unchanged (still `AutoPostBookingPaymentAction` crediting the full paid amount to 4050) — only the doctor-fee calculation changes; the hospital's fixed share is a derived reporting figure (revenue minus doctor expense), not a separate posted account.

### Duplicate doctor-fee calculators
- `Modules/Doctor/Services/ClaimCalculator.php` is changed to delegate to `DoctorClaimsService` for the per-doctor amount (or is deleted and its callers repointed to `DoctorClaimsService`), eliminating the possibility of the two disagreeing. This satisfies the manual's "do not duplicate the core calculation in more than one place" instruction (section 10 of the original request) even though it isn't a claim-lifecycle rule per se.

### Depreciation
- New `AutoPostDepreciationAction`, intended to run monthly (via the project's existing scheduler mechanism — check `routes/console.php`/`app/Console/Kernel.php` equivalent for how other periodic jobs, if any, are registered, and follow that convention; this project has no existing Jobs directory, so a scheduled Artisan command is the natural fit consistent with "no new architecture").
- For each depreciable fixed asset (buildings 1120/1121, medical equipment 1130/1131, furniture 1140/1141, computers 1150/1151, vehicles 1160/1161), posts `Dr 526x (depreciation expense, by asset class) / Cr 11x1 (accumulated depreciation, contra-asset)` for that month's depreciation amount, computed from asset cost, useful life, and accumulated depreciation to date. Never writes to the asset's original cost field. Idempotent per asset per month (e.g. key `depreciation:{asset_id}:{year}-{month}`).
- This requires confirming what fixed-asset model/table already exists in the codebase (the exploration did not find one) — if none exists, a minimal `assets` table (cost, useful_life_months/years, asset_class, accumulated_depreciation, acquired_at) is needed; keep it minimal and reporting-focused, not a full fixed-asset-register feature.

### Allocation rules (shared expenses)
- Add a minimal `allocation_rules` structure (e.g. a table mapping a shared-expense account or category to a set of cost centers with percentage columns) with no rows/percentages populated by this work — purely the schema/config surface for an administrator to fill in later. No posting action consumes it yet (out of scope to auto-apply allocations without administrator-supplied percentages).

### Cost-center profitability reporting
- No new schema needed (cost_center column + account `group` already sufficient). Confirm whether a reporting query/endpoint already exists (the exploration didn't confirm one) and, if not, add a query consistent with the manual's example SQL (group by `cost_center`, sum debit/credit by account group, for a date range) — reuse existing reporting-module conventions (`Modules/Reporting`) rather than a new module.

## Testing Decisions

- Tests belong in `tests/Feature/...` mirroring the existing module layout (`tests/Feature/Insurance/...`, `tests/Feature/Doctor/...`, `tests/Feature/Surgery/...`, `tests/Feature/Accounting/...`), following the same pattern as `InsuranceClaimAccountingTest`, `ProcessBundleSupplyAccountingTest`, `PayBookingAccountingTest`, `DevelopmentTreasuryFeeTest`, `JournalServiceIdempotencyTest` — call the Action classes (or the controller endpoint that triggers them, matching whichever pattern the file being edited already uses) and assert on the resulting `JournalEntry` rows (accounts, amounts, `cost_center`, debit==credit), not on internal calculation intermediates. This is the existing, correct seam — no new test seam is introduced.
- A good test here asserts observable ledger state (which accounts moved, by how much, tagged with which cost center) and, where relevant, the doctor's resulting payable/paid balance — never internal method call counts or private state.
- Modify existing tests where behavior intentionally changes:
  - `ProcessBundleSupplyAccountingTest` — update the 5115 assertion to 4070.
  - `InsuranceClaimAccountingTest` — update submission to assert department-specific revenue account and company-specific receivable account; update collection to assert bank + withholding + bad-debt split instead of cash-only.
- Add new tests for:
  - `AutoPostInsuranceDoctorCashPaymentAction` — posts 5130/1010, never touches 2010, idempotent, reversed on full rejection.
  - Insurance supplies consumption — posts 5010/5020 against 1051 at claim submission, not reversed on rejection.
  - Laser fixed-revenue doctor calculation — doctor fee = remainder after fixed revenue, discount reduces doctor's share not the fixed revenue.
  - Partial approval end-to-end: `approved_amount < claim amount` → collection posts bank + withholding + bad-debt correctly, total debits == total credits.
  - `AutoPostDepreciationAction` — posts correct expense/contra-asset accounts, idempotent per asset per month, never changes asset cost.
  - Doctor-fee-calculator consolidation — a test proving `ClaimCalculator`'s output (or its replacement) matches `DoctorClaimsService`'s output for the same doctor/period, across at least one dept_fees-override case, one dev-fee-deduction case, one surgery/lasik supplies case, and Pentacam exclusion.
  - Idempotency test for every new/changed action, following `JournalServiceIdempotencyTest`'s pattern.
- Run the full existing accounting/doctor/insurance/surgery test subset after each group of changes (not the whole suite each time), per project convention (`php artisan test --compact` with a file/filter), then the full suite once before calling the work done.

## Out of Scope

- Any change to the chart of accounts' numbering/grouping (already correct).
- Renaming `CostCenter::Pentacam` to `CC-PENTA` or migrating historical `journal_entries.cost_center` values.
- Introducing a global/system-wide withholding tax rate — withholding is per-company, administrator-configured, never system-defaulted.
- Adding a new `ClaimStatus` state for partial approval.
- Auto-computing or defaulting any allocation-rule percentage.
- A full fixed-asset register feature (acquisitions, disposals, asset transfers between cost centers) — only the minimum needed to post monthly depreciation.
- Switching to accrual-basis patient receivables (account 1048) — the manual explicitly marks this as a pending administrative decision, not part of this work.
- Per-doctor detailed payable sub-accounts (2201–2235) or per-supplier/employee detail accounts (2301–2324, 2401–2416) — these remain unimplemented roll-ups (2010/2020/2030) as today; not required by any rule in this spec.
- A UI/report screen for cost-center profitability beyond what's needed to prove the underlying query/data is correct (visual dashboard polish is separate follow-up work).
- Any change to how cash Clinic/Lab bookings, Pentacam, or the development-treasury-fee transfer currently work — these already match the manual and must not regress.
- Fixing unrelated pre-existing bugs incidentally discovered during exploration that the manual does not require correcting (e.g., `computeInsuranceSurgeryShare()`'s unused `$center` variable) beyond what naturally gets touched while implementing the above — no unrelated refactor pass.

## Further Notes

- Two open items need an administrator decision before their respective pieces can be considered complete, per the manual's own acknowledgment that these are pending: (1) whether/when to switch to accrual-basis patient receivables (1048) — explicitly out of scope here; (2) the actual withholding percentage per insurance company must be entered by an administrator after the `withholding_pct` field ships — the system will not compute correct withholding until that data exists, so the first real insurance collection after deploy should be checked manually.
- `Modules/Booking/Models/InsuranceCompany.php` and `Modules/Insurance/Models/InsuranceCompany.php` being two models on one table is a pre-existing structural quirk; the new receivable-account/withholding fields must be added consistently to both rather than treated as an opportunity to merge them (that merge is a separate, unrelated refactor).
- The exploration found no existing fixed-asset table/model — implementers should re-confirm this before starting the depreciation work, since a table might exist under a name the search missed.
- The exploration found no existing periodic-job/scheduler mechanism in this codebase (no Jobs directory, no observed cron wiring) — implementers should check `routes/console.php` and any deploy documentation before assuming a scheduled command is the right mechanism, in case there's server-level cron already calling specific artisan commands.
