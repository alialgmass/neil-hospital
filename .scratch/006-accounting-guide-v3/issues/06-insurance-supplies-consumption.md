# 06: Insurance supplies consumption — never charged to the doctor

**What to build:** ~~Add~~ **Discovered during work: this posting already existed.** `ProcessBundleSupplyAction` posts inventory-consumption entries (`Dr 5010/5020` / `Cr 1051`) at purchase price whenever supplies are recorded on ANY surgery/lasik case, regardless of pay method — it was never claim-triggered or claim-gated to begin with (there is no "insurance claim → replay supplies" step; supplies are recorded once, at the point of care, the same way for cash and insurance). The real gap was the *other* half of that same action: `postBundleChargeEntry()` (`Dr 2010 / Cr 4070`, ticket 02) fired unconditionally too, which is wrong for insurance — insurance doctor fees are a fixed amount (ticket 05, `Dr 5130 / Cr 1010`) and must never be adjusted by supplies, and insurance doctors have no 2010 balance to debit in the first place.

**Blocked by:** 05 (depends on the fixed-fee/no-2010 rule this enforces)

**Status:** ready-for-agent

- [x] Confirmed inventory consumption (5010/5020 → 1051) already posts correctly for insurance cases — no change needed there.
- [x] `ProcessBundleSupplyAction::process()` now accepts the surgery id and skips `postBundleChargeEntry()` entirely when the linked booking's `pay_method` is `insurance` (resolved via `Surgery::with('booking:id,pay_method')`).
- [x] `SurgeryController::supplies()` passes `$request->surgery_id` through so this can be resolved.
- [x] Test: an insurance-paid surgery with supplies posts 5010/1051 but posts nothing to 2010 or 4070.
- [x] Test: a cash-paid surgery is unaffected — still charges the doctor via 2010/4070 as before (ticket 02 behavior preserved).
- [ ] Not addressed (out of scope, no claim-rejection hook exists for this): since supplies aren't tied to `InsuranceClaim` at all in this codebase, there is nothing to reverse on claim rejection — the "never reverse supplies on rejection" rule is satisfied by construction (nothing here is reversible through the claim lifecycle), not by an explicit guard. Flag if the business later wants claim rejection to interact with supplies.
