# 05: Insurance doctor fee — dedicated cash-immediate posting

**What to build:** A new `AutoPostInsuranceDoctorCashPaymentAction` posts the insurance case's fixed doctor amount as `Dr 5130 (INSURANCE_DOCTOR_FEES) / Cr 1010 (CASH)` at claim submission, paid out immediately. Insurance-paid bookings must stop flowing through the generic accrual path (`SyncDoctorEntitlementAction` → `AutoPostDoctorDuesAction`, which posts to 2010) entirely — 2010 must never move for an insurance doctor fee. On full claim rejection, this entry is reversed if one was posted.

**Blocked by:** 04 (shares/extends the claim-submission posting flow)

**Status:** ready-for-agent

- [ ] `AutoPostInsuranceDoctorCashPaymentAction` posts `Dr 5130 / Cr 1010` for the case's fixed doctor amount (reusing the existing `doctor_service` pivot / `services.default_dr_fee` resolution logic), fired alongside `AutoPostInsuranceClaimAction::onSubmit()`.
- [ ] Idempotent per claim (re-submitting/retrying never double-posts).
- [ ] `SyncDoctorEntitlementAction`/`AutoPostDoctorDuesAction` no longer post anything to 2010/5110/5120 for insurance-paid bookings.
- [ ] Test: submitting an insurance claim posts 5130/1010 for the fixed doctor amount and never touches 2010.
- [ ] Test: fully rejecting the claim reverses the 5130/1010 entry (via the existing `JournalService::reverse()`), and total debits == total credits after reversal.
