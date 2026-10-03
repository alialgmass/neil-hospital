# 11: Fix possible double-deduction of supplies from doctor payable (Surgery/Lasik)

**What to build:** A Surgery/Lasik doctor's payable (2010) must be reduced by the supplies selling-price total exactly once, regardless of whether supplies are recorded before or after the doctor's fee is posted at payment time.

**Blocked by:** None (can start immediately — pre-existing bug, discovered during ticket 02, not caused by it)

**Status:** ready-for-agent

**Background:** `DoctorClaimsService::computeShareForPayment()`/`surgeryShareForPayment()` nets the doctor's posted dues against whatever `surgeries.supply_total` holds *at the moment of payment*. `ProcessBundleSupplyAction::postBundleChargeEntry()` (ticket 02) *separately* debits 2010 for the same supplies total at selling price when supplies are recorded. If supplies are recorded before the payment that nets them out (e.g. surgery documented before the final installment is collected), both mechanisms subtract the same amount from the doctor's payable — a real double deduction of hospital-owed money from what the doctor is paid. If supplies are always recorded strictly after the doctor's fee is posted, there is no double-count today; this needs confirming against the actual booking/surgery workflow, not assumed.

- [ ] Confirm, from the real workflow (not just code reading), whether supplies can ever be recorded before the payment/installment that nets them out of the doctor's share, for at least one real Surgery and one real Lasik case shape.
- [ ] If double-deduction is reachable: pick one single place where supplies-to-doctor deduction happens (either `computeShareForPayment`'s net-of-supply calculation, or `ProcessBundleSupplyAction`'s ledger charge — not both), and make the other stop double-counting, without changing the final correct doctor-payable number for the already-working "supplies after payment" order.
- [ ] Add a regression test that posts supplies *before* the netting payment and asserts the doctor's payable is reduced by the supplies total exactly once.
- [ ] Add/keep a regression test that posts supplies *after* payment (today's common order) and asserts unchanged correct behavior.
- [ ] `ProcessBundleSupplyAccountingTest`, `DevTreasuryDeductionBeforeDoctorFeeTest`, and any other test exercising this path still pass.
