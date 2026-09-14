# 05: Insurance doctor fee — dedicated cash-immediate posting

**What to build:** A new `AutoPostInsuranceDoctorCashPaymentAction` posts the insurance case's fixed doctor amount as `Dr 5130 (INSURANCE_DOCTOR_FEES) / Cr 1010 (CASH)`, paid out immediately. Insurance-paid bookings must stop flowing through the generic accrual path (`AutoPostDoctorDuesAction`, which posts to 2010) — 2010 must never move for an insurance doctor fee.

**Blocked by:** 04 (originally scoped to extend the claim-submission posting flow)

**Status:** ready-for-agent

**Implementation note (discovered during work):** `AutoPostInsuranceClaimAction`/`InsuranceClaim` (the Insurance module's claim-lifecycle tracker: draft→submitted→approved→paid) and the doctor's actual insurance-fee accrual (`SyncDoctorEntitlementAction`, fired on booking create/update/cancel/delete for any `pay_method=insurance|contract` booking, via `doctor_entitlements`) are two separate, loosely-connected subsystems — the former never posted a doctor fee at all before this ticket. The real production doctor-fee pathway is `SyncDoctorEntitlementAction`, so that's where this was wired in (for `EntitlementSource::Insurance` only — `Contract` keeps the existing 2010 accrual path unchanged, since the spec's cash-immediate rule is specific to insurance companies). Wiring into `AutoPostInsuranceClaimAction::onSubmit()` instead would have missed every insurance booking that never gets a formal `InsuranceClaim` row.

- [x] `AutoPostInsuranceDoctorCashPaymentAction` posts `Dr 5130 / Cr 1010` for the case's fixed doctor amount (reuses the existing `doctor_service` pivot / `services.default_dr_fee` resolution already in `SyncDoctorEntitlementAction::resolveFee()`).
- [x] Idempotent per booking+amount+source (re-saving/retrying never double-posts) — key format `doctor_entitlement:{source}:{booking_id}:{amount}`.
- [x] `SyncDoctorEntitlementAction` no longer posts anything to 2010/5110/5120 for `pay_method=insurance` bookings; `Contract` bookings are unaffected.
- [x] Test: an insurance booking posts 5130/1010 for the fixed doctor amount and never touches 2010.
- [x] Test: cancelling/switching away from an insurance booking reverses the 5130/1010 entry (via the existing `JournalService::reverse()`), and re-saving unchanged never duplicates it.
- [ ] Not covered: `AutoPostInsuranceClaimAction::onReject()` does not reverse a doctor-cash entry, since the claim and the booking-level entitlement are separate systems — a claim rejection today does not cancel the booking. If the business wants claim rejection to also reverse the doctor's cash payment, that requires connecting these two subsystems, which is a bigger change than this ticket scoped — flag for a follow-up decision if needed.
