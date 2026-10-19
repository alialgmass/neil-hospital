# 03: Laser doctor fee — fixed hospital revenue, doctor gets remainder

**What to build:** Laser services get a configurable fixed hospital-revenue amount. The doctor's fee for a Laser booking becomes `(paid − development fee) − fixed hospital revenue`, computed by the consolidated `DoctorClaimsService`. A patient discount reduces the doctor's fee first, never the fixed hospital revenue (since `paid` already reflects the discount and the fixed revenue is a constant).

**Blocked by:** 01 (doctor-fee calculators must already be consolidated so this logic lives in one place)

**Status:** ready-for-agent

- [ ] New nullable decimal field on `services` for the Laser fixed hospital-revenue amount, used only for Laser-department services.
- [ ] `DoctorClaimsService` computes the Laser doctor fee as `(paid − development fee) − fixed_hospital_revenue` when the field is set; falls back to the existing percentage/fixed logic when it isn't (no regression for services not yet configured).
- [ ] Test: service price 1000, fixed hospital revenue 600 → doctor fee 350 (after 50 EGP dev fee), matching the spec's worked example.
- [ ] Test: a patient discount reduces the doctor's fee, not the fixed hospital revenue amount.
- [ ] Booking-payment posting to 4050 (Laser revenue) is unchanged — only the doctor-fee calculation changes.
