# 08: مستحقات الأطباء screen — no double counting

**What to build:** The doctor-claims screen and the monthly-settlement calculator count each booking exactly once: for insurance/contract bookings they read the persisted `doctor_entitlements` amount; for every other booking they keep the existing computed share. Net due per doctor = Σ (entitlements + computed shares) − Σ `dr_payments`. Persisted entitlements are visible in the breakdown.

**Blocked by:** 04

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `DoctorClaimsService::calculateClaims` / `summarizeAll` take the persisted entitlement for insurance/contract bookings and skip `computeDrShare` for them
- [ ] `ClaimCalculator` (monthly settlement / `getActiveDoctorsWithClaims`) gets the same adjustment
- [ ] A doctor with one insurance booking (entitlement 750) + one cash booking (computed 300) shows total 1050, insurance booking counted once
- [ ] `net_due` subtracts `dr_payments`
- [ ] Existing claims tests updated for the new source-of-truth split and kept green (none removed)
- [ ] The claims screen renders persisted entitlements in the per-doctor breakdown
