# Spec: Insurance / Contract Claims Collection Console (تحصيل مطالبات شركات التعاقد)

**Created**: 2026-10-12
**Status**: Draft — ready-for-agent
**Source**: Approved UI prototype (Claude artifact "تحصيل مطالبات التعاقد", 8 tabs, demo data). The prototype is a behavioural reference, not code to port.

---

## Problem Statement

Collections staff chase money from contracting companies (التأمين الصحي، نفقة الدولة، بنك الشفاء، جلوب ميد…) with no single place that shows where each claim stands. Today the Insurance module only has a flat claim list with five statuses (غير مسددة / مُرسلة / معتمدة / مرفوضة / مسددة) and a status modal. This causes:

- No view of the **collection cycle** (under review, resubmitted after rejection, closed), so approved-but-uncollected and stuck claims are invisible.
- No **ageing** of the outstanding balance per company; nobody sees what has been uncollected for more than 3 months.
- Cases registered by Reception for contract patients with a **zero price** are not visible to collections and can silently never become a claim (revenue leakage).
- When a contract patient pays **cash up front (أمانة)** while waiting for the company's decision, there is no record of that deposit, no refund workflow once the company approves, and no rule for what happens if the company refuses.
- Collection entry accepts any discount/withholding without a reason or an audit trail, and nothing flags unexplained differences between what was due and what was recorded.
- No consolidated management dashboard (counts, due vs collected vs outstanding, per-company summary) and no per-case printable statement.

## Solution

A **Collections console** (تحصيل مطالبات شركات التعاقد) in the Insurance area with eight views:

1. **لوحة التحكم** – invoice counts (total / made / not yet made / open claims), money card (total due, collected, remaining, patient deposits held, collected-% bar), amount per stage, per-company summary.
2. **الاستقبال** – cases Reception registered for contract-company patients; zero-priced ones flagged "waiting for pricing".
3. **الدفع المؤقت** – patient cash deposits awaiting the company's decision, with approve / reject actions and refund tracking.
4. **دورة التحصيل** – kanban board of claims across the stages, each card showing company, amount, case count and age; clicking opens the claim drawer.
5. **متابعة الشركات** – per-company table: claims, claimed, collected, rejected, withholding, remaining, and remaining split into ageing buckets (≤1 month, 1–2, 2–3, >3 months).
6. **كل المطالبات** – full claims table.
7. **التقارير** – case-level statement report filtered by company, department and patient name / national ID, with a per-case printable/copyable sheet.
8. **الرقابة والتدقيق** – audit flags plus a who-did-what-when log.

Claim drawer lets the collector advance the stage, or record a collection (amount received, discount % / value kept in sync, net shown live, mandatory reason when discount exceeds 10%).

## User Stories

### Pipeline & claim lifecycle
1. As a collections officer, I want every claim to sit in exactly one stage (draft, submitted, under review, rejected-resubmission, approved, paid, closed), so that I always know what to chase next.
2. As a collections officer, I want to move a claim to the next stage from its drawer, so that the board reflects reality without leaving the screen.
3. As a collections officer, I want the submission date and a company reference number stamped when a claim is first submitted, so that I can quote it to the company.
4. As a collections officer, I want a rejected claim to be resubmittable with the rejected amount and reason recorded, so that partial rejections don't kill the whole claim.
5. As a collections officer, I want a fully collected claim to be closable, so that finished work leaves my active board.
6. As a collections manager, I want illegal stage jumps (e.g. draft → paid) refused, so that the cycle can't be bypassed.
7. As a collections officer, I want each board column to show claim count and total value, so that I see workload and exposure per stage at a glance.
8. As a collections officer, I want each card to show claim number, company, value, case count and a colour-coded age badge (≤30 / ≤60 / >60 days), so that I can prioritise.
9. As a collections officer, I want collected/closed cards labelled "مُحصّلة" instead of an age, so that I don't mistake them for overdue.
10. As a collections officer, I want to see which of the claim's steps are done, current and pending in the drawer, so that I understand the cycle position.

### Collection entry
11. As a collections officer, I want to enter the amount received and either a discount percentage or a discount value, so that I can record it however the company's remittance states it.
12. As a collections officer, I want editing percentage to recompute the value and editing the value to recompute the percentage, so that the two never disagree.
13. As a collections officer, I want the net received amount shown live, so that I can check it against the bank remittance before confirming.
14. As a collections manager, I want a discount above 10% to require a written reason, so that large deductions are always justified.
15. As a collections manager, I want negative or non-numeric amounts treated as zero, so that bad input can't create negative collections.
16. As an accountant, I want a confirmed collection to post the existing collection journal entries (bank, prepaid withholding, shortfall write-off) against the company's receivable, so that the ledger matches the console.
17. As an accountant, I want collection to be idempotent, so that a double click never posts twice.
18. As a collections officer, I want the claim marked paid, with collector name and time recorded, so that responsibility is traceable.
19. As an accountant, I want the linked booking marked paid and any deferred lab-doctor dues released when a claim is collected (existing behaviour preserved), so that downstream modules stay correct.

### Reception queue
20. As a collections officer, I want to see every contract-patient case Reception registered, so that nothing is lost between Reception and Collections.
21. As a collections officer, I want zero-priced cases flagged "في انتظار التسعير", so that I know pricing is the blocker.
22. As a collections officer, I want summary counts (total / waiting for pricing / priced and ready), so that I see the backlog size.
23. As a collections officer, I want to open a reception case to see patient, membership number, company, department, service and price, so that I can verify before raising a claim.
24. As a collections manager, I want a reception case still unconverted after 3 days to raise a high-severity audit flag, so that revenue isn't silently lost.

### Pending deposits (أمانة)
25. As a cashier, I want a patient's cash payment taken while the company's decision is pending recorded as a deposit (not revenue), so that the books are right.
26. As a collections officer, I want a list of deposits with patient, company, service, amount, date, company decision and refund status, so that I can manage them.
27. As a collections officer, I want to record the company's decision (approved / rejected) on a deposit, so that the next step is clear.
28. As a collections officer, I want an approved deposit to be converted into a claim on the company in one action, so that I don't re-key the case.
29. As a cashier, I want the deposit refunded to the patient at conversion, with the refund recorded, so that the patient is not double-charged.
30. As a collections manager, I want a rejected deposit to become a private cash collection that is not refunded, so that the outcome of a refusal is explicit.
31. As a collections officer, I want conversion to be impossible twice for the same deposit, so that no duplicate claim or double refund occurs.
32. As a collections manager, I want summary figures for deposits: total, awaiting decision, refunds due, refunds done, so that I can reconcile cash held.
33. As a collections manager, I want deposits still held (not refunded, not rejected) shown on the dashboard, so that I see patient money we owe back.

### Dashboard
34. As a hospital manager, I want total invoices, made, not-yet-made and open-claim counts, so that I understand volume.
35. As a hospital manager, I want total due, collected, remaining and deposits held on one card with a collected-% bar, so that I see cash position in seconds.
36. As a hospital manager, I want amount and count per stage, so that I see where money is stuck.
37. As a hospital manager, I want a per-company card with claims, claimed, collected and remaining, so that I compare payers.
38. As a hospital manager, I want KPI tiles (claimed, collected, rejected/deducted, remaining with open-claim count) above all tabs, so that the headline numbers are always visible.

### Companies & ageing
39. As a collections officer, I want per-company totals including rejected and withholding, so that I can reconcile with each company's statement.
40. As a collections officer, I want the remaining balance bucketed by age (≤1 month, 1–2, 2–3, >3 months) from the submission date, so that I attack the oldest money first.
41. As a collections manager, I want totals across all companies in a footer row, so that the table reconciles to the dashboard.
42. As a collections officer, I want money not yet submitted excluded from ageing, so that drafts don't look overdue.

### Claims list & reports
43. As a collections officer, I want a table of all claims with stage, company, value, collected, remaining and age, so that I can search beyond the board.
44. As a collections officer, I want to filter the case report by company and department and search by patient name or national ID, so that I can answer a company's query quickly.
45. As a collections officer, I want a live result count, so that I know my filter worked.
46. As a collections officer, I want a per-case statement sheet (patient, national ID, age, gender, phone, address, company, membership number, service, department, price, claim reference) I can copy to the clipboard, so that I can paste it into an email or portal.
47. As a collections officer, I want a copy fallback when the browser blocks clipboard access, so that copying always works.
48. As an accountant, I want the existing monthly Excel export of claims to remain available from the console, so that month-end routines don't change.

### Audit & control
49. As a hospital manager, I want an audit-flags panel with total and high-severity counts, so that I see risk first.
50. As a hospital manager, I want a flag when an approved claim has gone uncollected for more than 15 days, so that cash-in doesn't stall.
51. As a hospital manager, I want a flag when rejected amount exceeds 15% of the claimed amount, so that I review rejection causes.
52. As a hospital manager, I want a flag when the deduction on a collected claim exceeds 10% of what was due, so that unsupported deductions stand out.
53. As a hospital manager, I want a flag when due minus (collected + deduction) is not zero on a collected claim, so that unexplained differences are caught.
54. As a hospital manager, I want a flag when a claim line's price differs from the approved service price list, so that price tampering is visible.
55. As a hospital manager, I want a flag when outstanding money is older than 90 days, so that bad debt is escalated.
56. As a hospital manager, I want a flag for the same patient + service appearing in more than one claim, so that duplicates are reviewed.
57. As a hospital manager, I want flags sorted high severity first and a friendly "all balanced" message when there are none, so that the panel is actionable.
58. As an auditor, I want every stage move, collection, company decision and deposit conversion logged with user, time, action, detail and claim number, so that nobody can change numbers unseen.
59. As an auditor, I want the log newest-first and read-only, so that it can't be edited.

### Access & UX
60. As a hospital administrator, I want the console governed by a dedicated permission (view, advance stage, record collection, manage deposits), so that only authorised staff can move money.
61. As a user, I want Arabic RTL layout with tabular numerals and EGP formatting, so that figures are legible and consistent with the rest of the system.
62. As a user on a phone or tablet, I want the board to scroll horizontally and tables to scroll within their card, so that nothing breaks at small widths.
63. As a user, I want dark and light themes consistent with the rest of the app, so that the console matches the system.
64. As a collections officer, I want skeleton placeholders while deferred dashboard data loads, so that the page doesn't feel empty.

## Implementation Decisions

**Reuse, don't replace.** The Insurance module's claim record, company record, claim state machine, update action and the accounting auto-post action for claims stay the system of record. The console is a new read/act layer plus a small set of additions. Accounting rules already aligned to the accounting guide (recognition on service day, collection basis = approved amount, prepaid-withholding asset, shortfall write-off, idempotency keys) are **not** redefined.

**Claim stages.** Extend the existing claim state machine from 5 to 7 stages: draft → submitted → under review → approved → paid → closed, plus rejected → resubmitted (back into submitted/under review). Existing states and labels are kept; new states each carry an Arabic label registered in the shared label map. All stage comparisons use state classes, never raw strings (project rule). Transitions are enforced centrally; the board's "advance" action asks the state machine for the single legal next step.
- *Open point for confirmation:* the prototype's "إعادة تقديم مرفوض" is treated as a state reached from rejected; the existing terminal rejected behaviour (reversing recognition) must be revisited so a resubmitted claim re-recognises revenue idempotently.

**Collection input.** The collection action takes: amount received, discount value (percentage is a UI convenience derived from value), and reason. Rule: discount ÷ amount > 10% requires a non-empty reason (validated server-side, not only in the UI). The existing collection posting continues to derive its basis from approved amount and the company's configured withholding percentage; any recorded deduction beyond the configured withholding is surfaced by audit flags and carries its reason. *Open point:* whether excess deduction should post to a dedicated account or remain a flagged shortfall write-off — confirm with accounting guide v3 owner before implementation.

**New data.**
- Claim: add collector (user), collection timestamp, deduction amount, deduction reason, rejected amount (for partial rejection / resubmission), rejection note. Existing rejection reason field is reused where possible.
- Pending deposit: new record — patient, membership number, company, service, deposit amount, payment date, company decision (waiting / approved / rejected), refunded flag, converted flag, resulting claim reference. Deposits post to the existing patient-advances liability account on receipt; refund reverses it at conversion; a rejected deposit is reclassified to private cash revenue per the department's usual revenue account. New model ships with factory and seeder (project rule).
- Claim audit log: either the existing activity-log service (preferred; already used by the claim update action) with a dedicated module key, or a thin claims log if the activity log can't carry claim number and structured detail. Decide by inspection at build time.

**Reception queue.** Sourced from existing bookings whose payment method is insurance/contract and which have no claim yet; "zero price" is derived from the booking/service price. No new reception table.

**Read model.** One dashboard/board/ageing read service computes: counts, money card, per-stage totals, per-company rows with ageing buckets (age measured from submission date to today; unsubmitted claims excluded; paid/closed claims have zero outstanding), KPI band, and audit flags. All money comes from the claim record plus company share and rejected/paid/deduction fields; outstanding = company share − rejected − paid − deduction for submitted-and-later claims that aren't paid/closed. Rounding is to whole pounds for display only.

**Audit flags** are computed (not stored) from current data with these thresholds: reception case unconverted >3 days (high); approved and uncollected >15 days (high); rejected >15% of claimed (medium); deduction >10% of due (high); due ≠ collected + deduction beyond 1 pound (high); line price ≠ service price list (medium); outstanding older than 90 days (medium); duplicate patient + service across claims (medium). Thresholds live in one configuration location rather than being scattered.

**Controllers & routes.** Thin controllers over actions: one page controller rendering the console with deferred props per tab; action endpoints for advance stage, record collection, set deposit decision, convert deposit; reuse the existing export endpoint. Frontend uses Wayfinder route functions; page components follow existing Insurance page patterns and shared Modal component. Single-root Vue components; deferred props with skeletons.

**Permissions.** New permissions added through the existing permission seeding approach: view console, advance stage, record collection, manage deposits. Hide-by-permission in the UI plus route middleware.

**Currency & locale.** EGP, tabular numerals, Arabic labels in the lang files rather than hard-coded in components.

## Testing Decisions

**What makes a good test here:** exercise behaviour through the public HTTP seam (requests in, Inertia props / redirects / database state / journal entries out). No assertions on private methods, query shapes or component internals.

**Seam: one — HTTP feature tests on the Insurance module** (agreed with the user). Everything below is reached through endpoints and asserted via response props, claim/deposit state, activity log, and journal entries.

Scenarios to cover:
- Stage progression: each legal advance succeeds; illegal jumps are refused; submission stamps date and reference; rejected → resubmission path.
- Collection: net/deduction recorded; >10% discount without reason is refused (422), with reason accepted; negative/garbage amounts rejected; double-submit is idempotent; correct journal entries (bank, withholding asset, shortfall); booking marked paid; deferred lab dues released.
- Deposits: decision approve/reject; convert creates exactly one claim and marks refund; second convert refused; rejected deposit not refundable and reclassified; ledger effects for receipt / refund / reclassification.
- Dashboard props: counts, due/collected/outstanding, deposits held, per-stage and per-company aggregates against a seeded multi-company dataset (mirrors the prototype's seven claims across all stages).
- Ageing: bucket boundaries at 30/60/90 days; drafts excluded; paid/closed zero outstanding.
- Audit flags: each flag fires on its trigger and not just below its threshold; sort order; empty state.
- Reports: filters by company, department, name / national ID; statement payload fields.
- Permissions: each action refused without its permission; read-only user sees data but no action controls payload.
- Audit log: each state-changing action records user, time, action, detail, claim number.

**Prior art:** the existing Insurance claim accounting feature test (journal assertions with the accounts seeder), the booking-insurance-claim tests, the insurance-claims report test, and the booking permission tests for hide-by-perm behaviour. Use factories; PHPUnit classes only; run per-file, not the full suite, until the end.

## Out of Scope

- Pixel-for-pixel reproduction of the prototype; it is a behavioural and layout reference using demo data.
- Online submission to companies' portals / EDI; "submitted" is a manual status.
- Changing company price lists, the chart of accounts, or the accounting guide's existing claim entries (beyond the two open points noted).
- A separate clipboard/print engine beyond copy-to-clipboard and the browser print stylesheet.
- Patient-facing portals or notifications to patients/companies.
- Changing doctor entitlement rules for insurance bookings (specs 004 stays authoritative).
- Bulk import of remittance statements.

## Further Notes

- The prototype's pill colours, stage glyphs and dark-mode tokens are reference only; map them to the app's existing design tokens.
- Prototype dates are fixed to 2026-10-10/11 for demo; the real system must use the server date throughout.
- Prototype stores everything in memory and has no user model; the real log uses the authenticated user.
- Two open accounting questions are flagged in Implementation Decisions (resubmission re-recognition; destination of deductions beyond configured withholding). Resolve both before the first collection-posting change ships.
- Production is MySQL and tests run on SQLite: name any new long foreign keys explicitly (64-character identifier limit).
- UI changes will not appear until `npm run build` / `npm run dev` is run.
