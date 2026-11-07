# Spec: Align accounting engine with الدليل المحاسبي v2.0 (Al-Nour Eye Hospital)

**Triage label:** `ready-for-agent`
**Source document:** `اخر تحديث للدليل المحاسبى osama.docx` — "الدليل المحاسبي الكامل — مستشفى النور لطب وجراحة العيون — الإصدار 2.0، أغسطس 2026"
**Module:** `Modules/Accounting` (plus touch points in `Modules/Booking`, `Modules/Insurance`, `Modules/Doctor`, `Modules/HR`, `Modules/Surgery`, `Modules/Inventory`)

---

## Problem Statement

The hospital's finance team has issued a consolidated, authoritative accounting guide (v2.0). The accounting engine currently in the system was built against an earlier, partial understanding of the chart of accounts and the posting rules. As a result:

- The **development fund** (خزنة التطوير, 50 ج deducted from every service) does not exist in the system at all — every cash receipt is posted in full to the main treasury, so the reception-level development sub-fund can never be reconciled and doctor shares are computed on the wrong base.
- **Doctor shares are computed on the wrong base and with the wrong model.** The guide mandates: revenue booked in full at the amount actually paid; the doctor share is an independent expense (5110/5120/5130), never netted against revenue; and the share is calculated per section (clinic 50%, labs per-doctor %, laser = remainder after a fixed center revenue, pentacam 0, surgery/lasik = paid − 50 − supplies-at-sale-price, insurance = flat per case). The current `DoctorClaimsService` does not subtract the 50 ج development fund, values surgery/lasik supplies at **purchase** price instead of **sale** price, and has no laser "fixed center revenue" model.
- **Insurance revenue is posted to a single account (4110)** regardless of department. The guide requires the claim revenue to be split by department (4110 عيادة … 4150 ليزر / surgery 4130) and every insurance journal line tagged with a **payer dimension** (جهة التعاقد) using the same codes as the per-payer receivables.
- **Insurance receivables are a single account (1030).** The guide requires **17 per-payer sub-accounts (1031–1047)** so each contracting party's receivable balance and collection can be tracked.
- **Withholding tax at source (خصم و إضافة) is not modelled.** When an insurance claim is collected, the payer withholds a tax percentage and remits it to the tax authority. The guide requires this to be booked as a prepaid-tax **asset** (1080 ضريبة دخل مخصومة من المنبع), not an expense, so it can be offset against the year-end income-tax liability. Today the full `paid_amount` is debited to cash and any shortfall is written off as bad debt — conflating withholding with genuine claim reduction.
- **The insurance doctor fee** (5130) is never posted automatically. The guide requires it to be paid **in cash on the day of service**, debited straight to 5130 against the main treasury (1010), explicitly **bypassing** the doctor payable account (2010).
- **The supply charge to the doctor** in surgery/lasik bundles is currently credited to a revenue account (4230 / 4210). The guide's closing decisions require this to be a **contra-expense (5115)** that reduces net doctor fees, not revenue.
- **Fixed-asset depreciation is entirely manual.** The guide requires monthly automated depreciation postings (5261–5265 against contra-accumulated-depreciation accounts 1121/1131/1141/1151/1161).
- **Per-doctor, per-supplier, and per-employee payable detail** lives only in module tables, not in the chart of accounts. The guide requires control-account structure: 2201–2235 (doctors), 2301–2324 (suppliers), 2401–2416 (employees) under their master accounts.
- **Cost centers** exist as a string enum on `journal_entries` but there is no `cost_centers` table, the enum labels/codes diverge from the guide's seven canonical centers (e.g. `CC-PENTACAM` vs `CC-PENTA`), `AutoPostBookingPaymentAction::costCenter()` throws on any department it does not explicitly match (Pentacam is missing and there is no default arm), and there is no shared-expense allocation mechanism or center-profitability report.
- **Payroll** posts only net salary against a single employee payable. The guide requires gross salary (5210), the hospital's social-insurance share as a separate expense (5211) and liability (2041), and a remittance entry.
- **Liability account numbering diverges from the guide.** Current: 2030 = VAT, 2040 = employee payable. Guide: 2030 = net-salary payable master, 2040 unused, 2041 = social insurance, employees 2401–2416. The finance team wants the system renumbered to match the guide exactly.
- **Inventory count adjustments** (عجز / زيادة الجرد) have no posting path.

The finance team cannot produce a correct trial balance, income statement, balance sheet, or — most importantly — the **per-cost-center profitability report** that drives their management decisions, because the underlying postings do not follow the approved guide.

## Solution

Bring the accounting engine into full conformance with الدليل المحاسبي v2.0 so that, for every business event the hospital records (cash booking payment, insurance claim lifecycle, supply consumption, doctor payout, payroll, purchase, depreciation, inventory count), the resulting journal entries match the guide's worked examples exactly — the same accounts, the same amounts, the same cost-center and payer dimensions — and the guide's three worked scenarios (the 6,500 ج cataract insurance case, the full May-2026 month, and the 20-cases-per-department trial example) reproduce line-for-line, including a balanced trial balance and the matching cost-center profitability report.

From the finance team's perspective:

- Every cash service automatically moves 50 ج to the development fund and the rest to the main treasury, while recognizing the full service revenue.
- Each doctor's share is calculated by the exact rule for their department, on the post-development-fund base, and shown as an independent expense.
- Insurance claims are tracked per payer, recognized as revenue by department on submission, and collected with the withholding tax correctly separated into a recoverable asset.
- Insurance doctors are paid their flat fee in cash on the service day without ever touching the doctor payable ledger.
- Fixed assets depreciate automatically every month.
- The chart of accounts carries full control-account detail for doctors, suppliers, and employees.
- The seven cost centers are real, every posting is auto-tagged to the right one from the booking's department, shared expenses are allocated by configurable rules, and a profitability screen compares centers month over month.

## User Stories

### Development fund (خزنة التطوير)

1. As a finance manager, I want a dedicated development-fund treasury account (1011) seeded in the chart of accounts, so that the reception's development sub-fund has a real ledger balance.
2. As a receptionist collecting a cash service payment, I want 50 ج of every service automatically moved to the development fund and the remainder to the main treasury, so that I do not have to split the cash manually.
3. As a finance manager, I want the full service revenue still recognized at its gross amount even though 50 ج is diverted, so that revenue reporting is not understated.
4. As a finance manager, I want the 50 ج development-fund deduction applied uniformly across every clinical department (clinic, labs, laser, pentacam, surgery, lasik), so that the rule has no exceptions.
5. As a finance manager, I want insurance-funded services **excluded** from the 50 ج development-fund deduction (it applies to cash collection only), so that claim amounts are not distorted.
6. As a finance manager, I want each development-fund transfer tagged with the originating booking's cost center, so that I can see which department funded the development pot.
7. As an auditor, I want the development-fund posting to be idempotent per booking payment, so that a retried payment request never double-transfers.
8. As a finance manager, I want spending from the development fund recordable as a normal treasury/journal outflow from 1011, so that the fund can be drawn down.

### Doctor-share calculation

9. As a finance manager, I want every doctor share calculated on the base "amount actually paid − 50 ج development fund", so that the doctor does not earn a share of the diverted development money.
10. As a clinic doctor, I want my share to be 50% of the post-development-fund base, so that it matches my agreement.
11. As a labs doctor, I want my share to be my individually-configured percentage of the post-development-fund base (e.g. د. حسناء حسين 27%, د. رضوى سامي 25%), so that different technicians are paid their own rates — which requires the executing doctor to be recorded on every labs booking.
12. As a laser doctor, I want the center to keep a fixed per-service revenue defined in the price list and my share to be the remainder of (base − fixed center revenue), so that any discount granted to the patient reduces my share first, not the center's revenue (unless the agreement says otherwise).
13. As a finance manager, I want pentacam services to generate **no** doctor-share expense at all, so that the device only carries its monthly depreciation as a direct cost.
14. As a surgery or lasik doctor, I want my due calculated as (base − consumables charged to me at sale price), with the 5120 expense recorded at the full base and the consumables handled via the 5115 contra-expense, so that my payout matches the guide.
15. As an insurance-case doctor, I want a flat per-case fee (from the service definition) paid to me in cash on the day of service, so that I am not left waiting for the claim to be collected.
16. As a finance manager, I want revenue always booked in full at the paid amount and the doctor share always booked as a separate expense (never netted against revenue), so that the center's net revenue in the profitability report is meaningful.
17. As a finance manager, I want any patient discount or exemption in clinic/labs to reduce the doctor's share proportionally (because the % is applied to the amount actually paid, not the list price), so that the doctor and center share the discount.
18. As a finance manager, I want the same doctor-share rule used both at payment time (per-installment) and in the aggregate claims report (whole booking), so that the two never disagree.
19. As a finance manager, I want fixed/flat per-case fees attributed to the first installment only, and percentage-based fees prorated across installments, so that partial payments are handled sensibly.

### Consumables / supply bundles (surgery & lasik)

20. As a finance manager, I want the standard consumables list (فتح غرفة العمليات 1,000/45, ميثيل 500/300, عدسة لينة 700/350, كيراتوم 160/70 — sale price / purchase price) seeded and shared between the surgery and lasik departments, so that both use one price list.
21. As a finance manager, I want consumables issued from inventory at **purchase** price to the cost-of-supplies expense (5010 surgery / 5020 lasik), so that inventory is relieved at cost.
22. As a finance manager, I want the consumables **charged to the doctor at sale price** via a contra-expense (5115) that reduces net doctor fees, **not** credited to a revenue account, so that the center's margin on consumables shows as reduced cost rather than inflated revenue.
23. As a finance manager, I want the center's profit on consumables (sale − purchase, e.g. 2,360 − 765 = 1,595) to fall out of the postings automatically, so that I do not compute it by hand.
24. As a finance manager, I want the consumables charge deducted from the doctor's dues **once** (first installment), not on every installment, so that a multi-installment surgery does not over-recover.

### Insurance cycle

25. As a finance manager, I want 17 per-payer insurance-receivable sub-accounts (1031–1047: نفقة الدولة, التأمين الصحي, بنك الشفاء المصري, الأهلي للخدمات الطبية, جلوب ميد, ايجي كير, نقابة المحامين, ميد شور, ميد نت, ميد رايت, صحة وان, نقابة المعلمين, مصر للتأمين, ميد جولد, نقابة الأطباء, وادي النيل, جمعية رضى الخير) seeded under a non-postable 1030 master, so that each payer's outstanding balance is visible.
26. As a finance manager, I want claim submission to debit the correct per-payer receivable and credit the **department-specific** insurance revenue account (4110 clinic, 4120 labs, 4130 surgery, 4140 lasik, 4150 laser), so that insurance revenue is analyzable by service line.
27. As a finance manager, I want every insurance journal line tagged with a payer dimension using the payer's receivable code, so that I can run "insurance surgery revenue × نفقة الدولة" reports without proliferating revenue accounts.
28. As a finance manager, I want claim submission to recognize the **full claim value** as revenue and receivable on the service day (accrual for insurance), so that the hospital's earned income is not delayed to collection.
29. As a finance manager, I want the insurance doctor's flat fee posted on the service day as Dr 5130 / Cr 1010 (cash), never touching 2010, so that the doctor payable ledger stays clean for cash cases only.
30. As a finance manager, I want the consumables cost for an insurance case posted from inventory (Dr 5010 / Cr 1050) on the service day, so that inventory is relieved when the supplies are used, not when the claim is paid.
31. As a finance manager, I want claim approval to record `approved_amount` with **no journal entry**, so that an administrative status change does not touch the ledger.
32. As a finance manager, I want claim collection to debit bank (1020) for the transferred amount, debit prepaid withholding tax (1080) for the tax withheld at source, and credit the per-payer receivable for the full original amount, so that the receivable closes exactly and the withholding is preserved as a recoverable asset.
33. As a finance manager, I want the withholding-tax amount derived from a configurable rate (or an explicit entered value), so that I am not hardcoding a percentage.
34. As a finance manager, I want a fully rejected claim to reverse the original submission entry at its exact original amount (Dr revenue / Cr receivable), so that revenue and receivable are not left overstated.
35. As a finance manager, I want the consumables entry for a rejected claim **not** reversed (the supplies were really consumed and become a hospital loss), so that the ledger reflects reality.
36. As a finance manager, I want the insurance doctor's cash fee reversed on rejection **only if** the doctor agreement makes the fee contingent on claim acceptance, so that the reversal follows the contract.
37. As a finance manager, I want a partially-approved claim (e.g. 5,500 approved of 6,500) to collect bank + withholding for the approved portion and write off the rejected difference to bad debt (5300), so that the receivable still closes to zero.
38. As a finance manager, I want the year-end income-tax settlement to net the accumulated 1080 balance against the income-tax liability (2070), refunding or carrying forward any excess, so that withholding is ultimately reconciled (settlement mechanics may be a manual journal, but the accounts must exist).

### Chart-of-accounts structure

39. As a finance manager, I want per-doctor payable sub-accounts (2201–2235) under a non-postable 2010 master, so that each doctor's running balance is a real ledger account.
40. As a finance manager, I want per-supplier payable sub-accounts (2301–2324) under a non-postable 2020 master, so that each supplier's balance is a real ledger account.
41. As a finance manager, I want per-employee net-salary payable sub-accounts (2401–2416) under a non-postable net-salary master, so that each employee's payable is a real ledger account.
42. As a finance manager, I want the liability accounts renumbered to match the guide exactly (2030 = net-salary payable master, 2041 = social insurance payable, 2050 = patient advances, 2060 = accrued expenses, 2070 = income-tax payable), with existing journal history migrated to the new codes, so that the system and the printed guide agree.
43. As a finance manager, I want gross-asset and contra-accumulated-depreciation account pairs seeded (1120/1121 buildings, 1130/1131 medical equipment, 1140/1141 furniture, 1150/1151 computers, 1160/1161 vehicles), so that net book value = cost − accumulated depreciation.
44. As a finance manager, I want the prepaid-withholding-tax asset (1080) and the seven per-center cash tracking accounts (1090–1096) seeded, so that the guide's asset section is complete.
45. As a finance manager, I want the equity section to carry current-year net profit (3030) closing to retained earnings (3020) at year end, and owner drawings (3040) closing to capital (3010), so that year-end closing has its accounts.
46. As a finance manager, I want contra-revenue accounts for patient discounts (4910) and revenue refunds (4920), so that gross revenue and net revenue are both visible.
47. As a finance manager, I want the pentacam revenue account aligned to the guide (4060, "إيرادات وحدة البنتاكام") and the "إيراد بيع مستهلكات" account (4070) reconciled with the 5115 contra-expense decision, so that there is one unambiguous treatment of consumables sold to doctors.
48. As a developer, I want every account code the engine posts to represented in the `AccountCode` enum and resolvable via `AccountResolver`, with no hardcoded code strings anywhere, so that the single-source-of-truth invariant holds.
49. As a developer, I want parent/summary accounts (all the new masters: 1030, 2010, 2020, net-salary master, 1090 group, etc.) flagged non-postable, so that `JournalService::record()` rejects any attempt to post to them directly.
50. As a finance manager, I want `docs/accounts-map.md` regenerated to reflect the v2.0 chart and trigger map, so that the human-readable reference stays accurate.

### Cost centers & analytical reporting

51. As a finance manager, I want a real `cost_centers` table seeded with the seven canonical centers (CC-CLINIC, CC-LAB, CC-PENTA, CC-LASER, CC-LASIK, CC-SURG, CC-INS) plus CC-ADMIN for support services, so that centers are data, not code constants.
52. As a developer, I want `journal_entries` to carry a `cost_center_id` foreign key (alongside or replacing the current string column), so that center reports are a simple GROUP BY.
53. As a finance manager, I want every revenue and direct-expense posting auto-tagged with the cost center derived from the originating booking's department, with **no** manual center selection in the UI, so that tagging is never forgotten or wrong.
54. As a developer, I want `AutoPostBookingPaymentAction` (and every other AutoPost action) to resolve the cost center for **every** department including Pentacam, with a safe default, so that a booking in any department never throws an unmatched-enum error.
55. As a finance manager, I want doctor-share expenses (5110/5120/5130) tagged with the **service's** cost center, not a generic "doctors" center, so that each center bears its own doctor cost.
56. As a finance manager, I want employee-salary expense (5210–5219) tagged CC-ADMIN, while the employee payable sub-accounts remain pure tracking accounts with no center, so that support-staff cost lands in one place.
57. As a finance manager, I want a configurable allocation-rules table that distributes shared expenses (rent, admin salaries, utilities) across the revenue-generating centers by defined ratios, so that fully-absorbed center profitability can be produced when needed.
58. As a finance manager, I want a "Cost Center Profitability Report" screen showing, per center and per period: total revenue, direct expenses, allocated shared expenses, net profit, and margin %, with a month-over-month comparison and a chart, so that I can compare center performance and make expansion/negotiation decisions.
59. As a finance manager, I want CC-SURG (cash surgery) and CC-INS (insurance surgery) kept as separate centers even though the operating room is shared, so that the very different margins of the two channels are measured separately.
60. As a finance manager, I want the profitability report's total row to reconcile exactly to the income statement's net profit/loss for the period, so that I trust the numbers.

### Depreciation

61. As a finance manager, I want an automated monthly depreciation posting that debits the depreciation expense sub-account (5261 buildings, 5262 medical equipment, 5263 furniture, 5264 computers, 5265 vehicles) and credits the matching contra-accumulated-depreciation account, so that I do not run it by hand.
62. As a finance manager, I want each asset class's monthly depreciation derived from its cost and useful life (buildings ~50y, medical equipment 5–10y, furniture 8–10y, computers 4–5y, vehicles 5y), so that the amounts are defensible.
63. As a finance manager, I want medical-equipment depreciation (5262) taggable to the cost center that operates the equipment (e.g. the pentacam device to CC-PENTA), so that high-value devices carry their own cost.
64. As an auditor, I want the monthly depreciation run idempotent per asset per month, so that re-running it does not double-post.

### Payroll

65. As a finance manager, I want payroll approval to post gross salary (5210) and the hospital's social-insurance share (5211) as expenses, crediting net salary to the per-employee payable and the combined social-insurance amount to 2041, so that the full labor cost is recognized.
66. As a finance manager, I want a social-insurance remittance entry (Dr 2041 / Cr 1010) available, so that paying the authority clears the liability.
67. As a finance manager, I want salary payment to debit the per-employee payable and credit the treasury, so that the payable clears per employee.
68. As an auditor, I want payroll accrual, payment, and remittance each idempotent, so that re-triggering a payroll event never double-posts.

### Inventory count adjustments

69. As a store keeper, I want an inventory count **shortage** (book > physical) posted as Dr 5010 (cost of supplies — shortage) / Cr 1050, so that inventory matches the physical count and the loss hits cost.
70. As a store keeper, I want an inventory count **surplus** (physical > book) posted as Dr 1050 / Cr 4220 (miscellaneous revenue), so that inventory matches the count and the gain is recognized.

### Worked-example conformance

71. As a finance manager, I want the guide's 6,500 ج cataract insurance case to reproduce exactly (1031 opens and closes at 6,500; 4130 +6,500; 5010 +2,000; 5130 +500; 1010 −500; 1020 +6,000; 1080 +500; 2010 untouched), so that I can validate the engine against the guide.
72. As a finance manager, I want the guide's full May-2026 month to reproduce its trial balance (189,870 / 189,870), income statement (net loss 5,197), and balance sheet (144,803), so that a whole-month replay is proven.
73. As a finance manager, I want the guide's "20 cases per department" trial example to reproduce its trial balance (823,400 / 823,400), income statement (net profit 207,800), and cost-center profitability table, so that the analytical layer is proven.
74. As an auditor, I want a standing invariant that total debits equal total credits after any sequence of postings, so that the ledger can never go out of balance.

### Migration & safety

75. As a developer, I want the account renumbering and new-account seeding delivered as reversible migrations that also migrate existing `journal_entries` and `accounts.balance` references, so that historical data stays consistent.
76. As a finance manager, I want existing posted journal entries left economically unchanged by the migration (only codes remapped, never amounts), so that prior periods still foot.
77. As a developer, I want the historical seeders (`database/seeders/Historical/*`) updated to the v2.0 chart, so that a fresh rebuild reproduces the same balances.
78. As an auditor, I want every new AutoPost path idempotent via `idempotency_key`, consistent with the existing engine, so that the idempotency guarantee is uniform.

## Implementation Decisions

### Scope

- **One spec, all workstreams.** The agent implements incrementally but against this single spec. Suggested delivery order: (1) chart-of-accounts restructure + renumbering migration; (2) cost-center table + auto-tagging + the `costCenter()` default-arm bug; (3) development fund + doctor-share rules; (4) insurance cycle (per-payer, dept-split revenue, withholding tax, same-day doctor cash); (5) consumables contra-expense (5115); (6) depreciation; (7) payroll gross/social-insurance; (8) inventory count adjustments; (9) allocation rules + profitability report screen.

### Chart of accounts

- **Renumber liabilities to match the guide exactly.** 2030 → net-salary payable master (non-postable), 2040 retired, 2041 → social insurance payable, 2050 → patient advances, 2060 → accrued expenses, 2070 → income-tax payable. The current 2030 (VAT) moves to a guide-consistent code or is retired if VAT remains out of scope — VAT automation stays out of scope but the account must not collide.
- New master (non-postable) accounts: 1030 (insurance receivable), 2010 (doctor payable), 2020 (supplier payable), net-salary master, 1090 group, 4100 (insurance revenue), plus existing 1000/1100/2000/2100/4000/4200/5000/5100/5200.
- New sub-account ranges: 1031–1047 (payers, 17), 2201–2235 (doctors), 2301–2324 (suppliers), 2401–2416 (employees). Sub-account rows may be seeded from the module tables (`doctors`, `suppliers`, `employees`) so the list stays in sync; the code allocation strategy (fixed seeded list vs. generated-on-create) is an open choice for the implementer, but the master accounts and the first-cohort codes in the guide must be seeded.
- New accounts: 1011 (development fund), 1080 (prepaid withholding tax), 1090–1096 (per-center cash tracking), 1120/1121, 1130/1131, 1140/1141, 1150/1151, 1160/1161 (gross asset + contra pairs), 2041, 2060, 2070, 3030, 3040, 4060 (pentacam), 4910/4920 (contra-revenue), 4110–4150 (insurance revenue by department), 5115 (contra-expense — supply cost recovered from doctor), 5211 (hospital social-insurance share), 5261–5265 (depreciation by asset class), 5300 (bad debt — already exists).
- **`AccountCode` enum is the single source of truth.** Every new code added there; every posting resolves through `AccountResolver`; `nonPostableCodes()` extended with every new master. `AccountResolver::mustBePostableAndActive()` already enforces the non-postable / has-children guard — no change needed there.
- `4070` "إيراد بيع مستهلكات" vs `5115` contra-expense: the **5115 contra-expense treatment wins** for consumables charged to the doctor (guide closing decision #2). `4230` (`DOCTOR_SUPPLY_COST_RECOVERY`) is retired in favor of `5115`. `4070`'s existing role as a service-level `revenue_account_id` override is preserved or migrated — the implementer picks a non-colliding code for whichever meaning survives, documented in `accounts-map.md`.
- `docs/accounts-map.md` and `specs/001-eye-hospital-hms/contracts/accounting.md` regenerated.

### Development fund

- A single reusable posting helper (a service method or a small action) computes the split for any cash service collection: `development = 50 (once per booking, on first payment)`, `treasury = paid − 50`, `revenue = paid (gross)`. Applied by `AutoPostBookingPaymentAction` for all non-insurance departments. Insurance bookings are already short-circuited in that action and stay excluded.
- The 50 ج transfer is its own journal line (Dr 1011 / Cr 1010) tagged with the booking's cost center, with an idempotency key derived from the booking (`dev_fund:{file_no}`), so it fires once regardless of installment count.
- The doctor-share base everywhere becomes `paid − 50` (only when a development-fund deduction actually applied to that booking).

### Doctor-share rules (`DoctorClaimsService` + `AutoPostDoctorDuesAction`)

Canonical rules (guide §2.7), applied identically in `computeShareForPayment` (per-installment) and `computeDrShare` (whole booking):

| Department | Doctor share | Center revenue | Expense acct |
|---|---|---|---|
| Clinic (CC-CLINIC) | 50% × (paid − 50) | remaining 50% | 5110 |
| Labs (CC-LAB) | per-doctor % × (paid − 50) | remainder | 5110 |
| Laser (CC-LASER) | (paid − 50) − fixed center revenue for the service | fixed price-list amount | 5110 |
| Pentacam (CC-PENTA) | none (no posting) | full paid amount | — |
| Lasik (CC-LASIK) | (paid − 50) − consumables at sale price | consumables sale price | 5120 |
| Surgery cash (CC-SURG) | (paid − 50) − consumables at sale price | consumables sale price | 5120 |
| Insurance surgery/lasik (CC-INS) | flat per-case fee (service definition), cash on service day | remainder of claim value | 5130 |

- Surgery/lasik: 5120 is debited at the full `paid − 50`; the consumables charged to the doctor at **sale price** are handled by the 5115 contra-expense line, not netted into 5120. Consumables deduction hits the **first installment only**.
- Labs requires `doctor_id` recorded on every labs booking; the per-doctor percentage comes from `doctor.dept_fees['labs']` (or equivalent). If absent, fall back to a configured default and log — do not post a wrong share silently.
- Laser: patient discount reduces the doctor's remainder first; center's fixed revenue is unaffected unless a per-agreement override says otherwise.
- Clinic/labs %: applied to the amount **actually paid** (post-development-fund), so discounts flow through proportionally.
- Revenue is **always** booked gross at `paid`; doctor share is **always** a separate expense; never net.

### Consumables (`ProcessBundleSupplyAction`)

- Seed the shared surgery/lasik consumables price list (sale/purchase pairs from the guide).
- Per case: Dr 5010 (surgery) or 5020 (lasik) / Cr 1050 at **purchase** price (inventory relief at cost); Dr 2010 / Cr 5115 at **sale** price (charge to doctor as contra-expense). Net doctor payout and center consumables margin fall out automatically.
- Replace the current Dr 2010 / Cr 4230 line with Dr 2010 / Cr 5115. Cost center = the service's center.

### Insurance cycle (`AutoPostInsuranceClaimAction`)

- `onSubmit`: Dr {per-payer receivable 1031–1047, resolved from `claim.ins_company` / payer mapping} / Cr {department insurance revenue 4110–4150, resolved from `claim` department}. Amount = full claim value. Payer dimension tagged on both lines. Cost center from department (CC-INS for surgery/lasik insurance, else the service center — confirm with the guide's CC-INS-only stance for operations; clinic/labs/laser insurance keep their own center).
- Same service day, still in the submit path (or a sibling action): Dr 5010 / Cr 1050 for consumables at purchase price; Dr 5130 / Cr 1010 for the flat doctor fee (bypassing 2010) — new `AutoPostInsuranceDoctorCashPaymentAction`.
- `onApprove`: no journal entry (unchanged).
- `onCollect`: Dr 1020 (bank) for transferred amount + Dr 1080 (prepaid withholding tax) for tax withheld / Cr {per-payer receivable} for the full original amount. Withholding amount from a configurable rate or an entered value on the collection. If approved < submitted (partial approval), the disallowed difference goes Dr 5300 / Cr {receivable} so the receivable still closes to zero. **Withholding is never bad debt** — the two are distinct lines.
- `onReject`: reverse the `onSubmit` entry at its exact original amount (already implemented — extend to the per-payer / dept-revenue accounts). Do **not** reverse the consumables entry. Reverse the 5130 cash fee **only** when `claim`/agreement marks the fee contingent (new flag or config).
- Year-end: 1080 nets against 2070 — a documented manual settlement journal; both accounts must exist and be postable.

### Cost centers

- New `cost_centers` table (code, name/label, is_active). Seed the seven canonical + CC-ADMIN. Reconcile the `CostCenter` enum codes/labels to the guide (`CC-PENTA` not `CC-PENTACAM`; keep enum as the typed accessor, back it with the table or keep both in sync).
- `journal_entries.cost_center_id` FK added. Existing string `cost_center` column data migrated to FKs; keep the string column during transition or drop it in the same migration set (implementer's call, but reports must read the FK).
- Every AutoPost/Process action resolves cost center from the originating booking's `Department`. Add a single shared `CostCenter::forDepartment(Department): self` with a **default arm** (fixes the current `AutoPostBookingPaymentAction::costCenter()` throwing on Pentacam / unmatched departments).
- Allocation-rules table (source expense account or category, target center, ratio) + a monthly allocation posting or a report-time allocation (implementer picks; report-time is lower-risk). Shared expenses (salaries, utilities, rent) are **not** posted to centers directly — they show in the total row and are allocated only for the fully-absorbed view.
- New "Cost Center Profitability Report" Inertia page under `resources/js/pages/` (prior art: `resources/js/pages/journal/CostCenters.vue`, `resources/js/pages/ledger/*`), backed by a reporting service that GROUP BYs `journal_entries.cost_center_id`. Its total row must equal `IncomeStatementService` net profit for the period.

### Depreciation

- New `AutoPostDepreciationAction` + a scheduled monthly command (prior art: none yet — follow Laravel scheduled-command conventions and the existing AutoPost action shape). Needs a `fixed_assets` representation (cost, useful life, class, in-service date, operating cost center) — if no such table exists, add one. Idempotent per asset per month. Dr 5261–5265 / Cr 1121/1131/1141/1151/1161. Medical-equipment line taggable to the operating center.

### Payroll (`AutoPostPayrollAction`)

- `onApprove`: Dr 5210 (gross) + Dr 5211 (hospital social-insurance share) / Cr {per-employee net-salary payable} (net) + Cr 2041 (employee + hospital social-insurance). All expense lines CC-ADMIN.
- `onPay`: Dr {per-employee payable} / Cr 1010 (unchanged shape, new per-employee account).
- New remittance path: Dr 2041 / Cr 1010.

### Inventory count adjustments

- Shortage: Dr 5010 / Cr 1050. Surplus: Dr 1050 / Cr 4220. Triggered from the inventory count/reconciliation flow (prior art: `AutoPostStockIssueAction`). Cost center from the department being counted, else CC-INV/CC-ADMIN.

### Migration & data

- Renumbering delivered as reversible migrations that remap `accounts.code`, repoint `journal_entries.debit_account_id` / `credit_account_id` if any FK is by code (they are by id — so mostly a code relabel), and update any `services.revenue_account_id` overrides. No amount is ever changed.
- `AccountsSeeder` and `database/seeders/Historical/*` updated to v2.0 so a fresh rebuild reproduces the guide's balances.

## Testing Decisions

### What makes a good test here

- Tests assert **external, observable accounting outcomes**, never internal method calls: the rows written to `journal_entries` (debit account code, credit account code, amount, `cost_center_id`, payer dimension, `source`, `idempotency_key` presence), the resulting `accounts.balance` deltas, and `treasury_entries` rows. They do not assert private helpers, call counts, or the shape of intermediate arrays.
- Each posting rule gets a test that arranges the triggering domain state (a `Booking` + `Service` + `Doctor` via factories, an `InsuranceClaim`, a `Payroll`, a `StockPermit`), invokes the AutoPost/Process action (or the controller endpoint that triggers it), and asserts the exact journal lines the guide prescribes.
- Idempotency is tested by invoking the trigger twice and asserting no duplicate rows and unchanged balances (prior art: `JournalServiceIdempotencyTest`).
- A standing **trial-balance invariant** test: after each scenario, `SUM(debits) == SUM(credits)` across `journal_entries`, and the sum of debit-nature account balances equals the sum of credit-nature balances.

### Seam

- **The existing per-Action seam.** Feature tests in `tests/Feature/Accounting/`, `tests/Feature/Insurance/`, `tests/Feature/Booking/`, `tests/Feature/Surgery/`, `tests/Feature/Inventory/`, plus a new `tests/Feature/HR/` and `tests/Feature/Accounting/DepreciationTest.php`, driving the AutoPost/Process actions and asserting `journal_entries` + `accounts` + `treasury_entries`.
- **No new seam is introduced.** The actions are already the natural, highest testable boundary between "a business event happened" and "the ledger changed."
- One dedicated **golden-scenario** test class (`tests/Feature/Accounting/GuideScenariosTest.php`) that replays, event by event through the real actions:
  1. the 6,500 ج cataract insurance case → asserts the guide's final per-account balances;
  2. the full May-2026 month → asserts trial balance 189,870/189,870, income statement net loss 5,197, balance sheet 144,803;
  3. the 20-cases-per-department example → asserts trial balance 823,400/823,400, net profit 207,800, and the cost-center profitability table.
  These use the guide's numbers as fixed expected values.

### Modules tested

- `Modules/Accounting` — `AccountResolver` (every new `AccountCode` resolves — extend `AccountResolverTest`), `JournalService` (non-postable rejection for every new master), `AutoPostBookingPaymentAction`, `AutoPostInsuranceClaimAction`, `AutoPostInsuranceDoctorCashPaymentAction`, `AutoPostPayrollAction`, `AutoPostDepreciationAction`, `IncomeStatementService` / `BalanceSheetService` / `LedgerService` reconciliation, the new profitability reporting service.
- `Modules/Doctor` — `DoctorClaimsService` per-department share rules incl. the 50 ج base (extend `DoctorClaimsReportTest`).
- `Modules/Surgery` — `ProcessBundleSupplyAction` 5115 contra-expense (rewrite `ProcessBundleSupplyAccountingTest`).
- `Modules/Insurance` — claim lifecycle (extend `InsuranceClaimAccountingTest`, `UpdateBookingInsuranceClaimTest`).
- `Modules/Inventory` — count adjustments (extend `AutoPostStockIssueActionTest` neighborhood).

### Prior art

- `tests/Feature/Accounting/AccountResolverTest.php`, `JournalServiceIdempotencyTest.php`, `JournalDeleteTest.php`, `PentacamRevenueTest.php`, `TreasuryEditDeleteTest.php`
- `tests/Feature/Insurance/InsuranceClaimAccountingTest.php`
- `tests/Feature/Surgery/ProcessBundleSupplyAccountingTest.php`
- `tests/Feature/Booking/ReverseBookingPaymentTest.php`, `SalesInvoiceInsuranceTest.php`, `UpdateBookingInsuranceClaimTest.php`
- `tests/Feature/Inventory/AutoPostStockIssueActionTest.php`
- `tests/Feature/Reports/DoctorClaimsReportTest.php`, `InsuranceClaimsReportTest.php`
- All are PHPUnit feature tests using `RefreshDatabase` and model factories — follow that exactly. Run with `php artisan test --compact --filter=...`.

## Out of Scope

- **VAT automation (2030 old / VAT).** No tax-rate configuration exists; the account must not collide with the renumbering but no automatic VAT posting is built.
- **Accrual basis for patient receivables (1048 / 1040 "ذمم مدينة — مرضى آجل").** The system stays on cash-received basis for patient (non-insurance) revenue. Switching to recognize revenue at service time with an open patient balance is a revenue-recognition policy change that needs an explicit separate decision (guide closing note #11). The account exists; nothing posts to it automatically.
- **Long-term bank loans (2110) amortization schedules.** Manual only.
- **Multi-currency, budgeting, forecasting.**
- **A full fixed-asset register UI.** Only the minimal `fixed_assets` data needed to drive monthly depreciation is in scope; a management screen for asset CRUD is not (may be a follow-up).
- **Historical restatement of prior closed periods** beyond the mechanical code remap — amounts in already-posted entries are never recomputed.
- **Backfilling development-fund transfers or per-payer splits onto historical journal entries** — the new rules apply from the migration forward; historical seeders are updated so a rebuild is consistent, but live historical data is only code-remapped.

## Further Notes

- The guide's own closing section ("ملاحظات ختامية") enumerates 11 required changes; this spec covers all of them plus the structural gaps found by reading the code:
  1. dept-split insurance revenue (4110–4150) — US 26
  2. 5115 contra-expense instead of 4230 revenue — US 22, decisions
  3. `AutoPostInsuranceDoctorCashPaymentAction` (Dr 5130 / Cr 1010) — US 29
  4. `AutoPostDepreciationAction` monthly — US 61–64
  5. `AutoPostInsuranceCollectionAction` withholding-tax split (1080) — US 32–33
  6. 17 per-payer sub-accounts (1031–1047) — US 25
  7. `cost_center_id` column + `cost_centers` table — US 51–52
  8. auto-link booking → cost center by dept — US 53–54
  9. shared-expense allocation rules — US 57
  10. Cost Center Profitability Report screen — US 58
  11. accrual-basis decision for patient receivables — **out of scope**, flagged for the finance team.
- Bug found while reading the code: `AutoPostBookingPaymentAction::costCenter()` is a `match` with no default arm and no `Department::Pentacam` case — any Pentacam booking payment (or a future new department) throws `\UnhandledMatchError`. Fixed as part of US 54.
- Divergence found: `CostCenter` enum uses `CC-PENTACAM`; guide uses `CC-PENTA`. `AccountCode::PENTACAM_REVENUE = '4090'` and `RETINA_REVENUE = '4060'`; guide puts pentacam at `4060` ("إيرادات وحدة البنتاكام") and has no separate retina revenue account. Reconcile during the chart restructure and document in `accounts-map.md`.
- Divergence found: `AccountCode` has `HEALTH_INSURANCE_REVENUE_OVERRIDE = '4070'` **and** `DOCTOR_SUPPLY_COST_RECOVERY = '4230'`; the guide's model has neither — consumables sold to the doctor is a contra-expense (5115). Both enum cases are reworked.
- The guide is written in Arabic; keep all account names, journal descriptions, and UI labels in Arabic consistent with the existing chart and `accounts-map.md`.
- The guide contains minor internal arithmetic inconsistencies in the prose (e.g. a "صافي مستحق طبيب الليزك = 15,000 − 2,360 = 12,640" line that contradicts the "12,590 after 50 ج development fund" rule stated everywhere else). Where the prose and the stated rule disagree, **the rule wins** (base is always `paid − 50`); the golden-scenario tests encode the rule-consistent numbers.
- Publishing: no issue tracker is configured for this repo (`/setup-matt-pocock-skills` has not been run). This spec is filed at `specs/003-accounting-guide-v2/spec.md` following the existing `specs/NNN-slug/` convention. Move it to a tracker issue with the `ready-for-agent` label once one is set up.
