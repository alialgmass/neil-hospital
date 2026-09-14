# 07: Accounting accrual for entitlements

**What to build:** Creating a doctor entitlement posts the doctor-payable accrual (Dr 5110/5120 by department / Cr 2010) once, using the existing `AutoPostDoctorDuesAction`, keyed `doctor_entitlement:{booking_id}`. Recalculating an entitlement reverses the prior accrual and posts the corrected amount. Voiding an entitlement reverses the accrual. Repeated booking saves post nothing new. Pentacam and zero amounts are skipped (inherited from the existing action).

**Blocked by:** 04, 05, 06

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] Entitlement creation posts Dr 5110/5120 / Cr 2010 once with key `doctor_entitlement:{booking_id}`
- [ ] Second save of the same booking posts no new journal entry
- [ ] Recalculation reverses and re-posts at the new amount
- [ ] Voiding (deal-type change, cancel, delete) reverses the accrual
- [ ] Pentacam / zero amount → no journal entry
- [ ] Existing `PayBookingAccountingTest` / `DoctorPaymentAccountingTest` / `ReverseBookingPaymentTest` stay green
