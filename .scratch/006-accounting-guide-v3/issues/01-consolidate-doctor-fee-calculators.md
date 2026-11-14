# 01: Prefactor — consolidate doctor-fee calculators

**What to build:** `Modules/Doctor/Services/ClaimCalculator.php` must never diverge from `Modules/Doctor/Services/DoctorClaimsService.php` for the same doctor/period. Make `ClaimCalculator` delegate its per-doctor amount to `DoctorClaimsService` (or remove `ClaimCalculator` and repoint its callers), so the doctors-list dashboard and the claims report always agree.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `ClaimCalculator` (or its replacement) produces the same claim amount as `DoctorClaimsService` for: a `dept_fees`-override doctor, a doctor whose fee is affected by the 50 EGP development-fee deduction, a surgery/lasik doctor whose fee is reduced by supplies, and a Pentacam booking (must be excluded, amount 0/absent).
- [ ] No caller of the old `ClaimCalculator` logic still computes fees independently of `DoctorClaimsService`.
- [ ] A test proves the two code paths (dashboard list and claims report) return identical amounts for the same doctor/period across the cases above.
- [ ] Existing `DoctorClaimsEntitlementTest` and any other doctor-claims tests still pass.
