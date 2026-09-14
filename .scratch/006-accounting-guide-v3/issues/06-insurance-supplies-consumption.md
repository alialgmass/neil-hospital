# 06: Insurance claim submission — supply/inventory consumption posting

**What to build:** When an insurance claim covers a surgery/lasik case with recorded supplies, submitting the claim posts inventory-consumption entries (`Dr 5010/5020` cost-by-department / `Cr 1051` inventory) at purchase price — the same accounting cash cases already get via `ProcessBundleSupplyAction`'s inventory half. This posting is never reversed on claim rejection, even a full rejection, because the supplies were physically used.

**Blocked by:** 05 (both extend the same claim-submission/rejection flow; sequencing avoids conflicting edits)

**Status:** ready-for-agent

- [ ] Insurance claim submission for a surgery/lasik case with supplies posts `Dr 5010/5020 / Cr 1051` per item at purchase price (reusing `ProcessBundleSupplyAction`'s existing per-item costing logic, not its doctor-charge half — insurance doctor fee is fixed, not supplies-adjusted).
- [ ] `AutoPostInsuranceClaimAction::onReject()` (full rejection) reverses revenue/receivable and the insurance-doctor-fee entry (ticket 05) but never reverses this supplies-consumption entry.
- [ ] Test: claim with supplies posts the correct 5010/1051 (or 5020/1051) entries at submission.
- [ ] Test: fully rejecting that claim leaves the supplies-consumption entry un-reversed while revenue/receivable/doctor-fee entries are reversed.
- [ ] Idempotent per claim/item.
