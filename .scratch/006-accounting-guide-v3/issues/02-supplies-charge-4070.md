# 02: Switch surgery/lasik doctor-supply-charge posting from 5115 to 4070

**What to build:** When supplies used on a Surgery-cash or Lasik case are charged to the doctor at selling price, the credit side of that journal entry must be `4070 (SUPPLIES_SALE_REVENUE)` instead of `5115 (SUPPLY_COST_RECOVERED_FROM_DOCTOR)`. The debit side (`2010 DOCTOR_PAYABLE`) and the amount (bundle selling price) are unchanged.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `ProcessBundleSupplyAction::postBundleChargeEntry()` posts `Dr 2010 / Cr 4070` at the bundle's selling price.
- [ ] `AccountCode::SUPPLY_COST_RECOVERED_FROM_DOCTOR` (5115) stays defined in the enum but is no longer referenced by any posting action.
- [ ] `ProcessBundleSupplyAccountingTest` updated to assert the 4070 credit instead of 5115, keeping its other assertions (inventory-consumption postings by category) unchanged.
- [ ] A total-debits-equals-total-credits assertion covers the full worked example (10,000 EGP surgery case) from the spec.
- [ ] No historical journal entries are rewritten — this only changes behavior for postings created after the change ships.
