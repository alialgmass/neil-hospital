# 05: Entitlement lifecycle on booking edit

**What to build:** Editing an insurance/contract booking keeps its doctor entitlement correct: changing the doctor re-points the entitlement and recalculates the amount from the new doctor's fee; changing the service recalculates the amount; changing نوع التعامل away from insurance/contract removes the pending entitlement; changing it to insurance/contract creates one. A settled entitlement is left untouched with a notice. At most one live entitlement per booking at any time.

**Blocked by:** 04

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `SyncDoctorEntitlementAction` handles recalculate / re-point / void / create from current booking state vs. existing entitlement
- [ ] Wired into `UpdateBookingAction` alongside the existing insurance-claim sync
- [ ] Change doctor → entitlement re-pointed, amount recalculated, still one row
- [ ] Change service → amount recalculated
- [ ] Save twice with no doctor/service change → still one row (idempotent)
- [ ] `pay_method` insurance → cash → pending entitlement removed
- [ ] `pay_method` cash → contract → entitlement created
- [ ] Editing a booking whose entitlement is `settled` → unchanged, notice shown
