# 07: Insurance claim collection — withholding tax, bank, and partial approval

**What to build:** A per-company withholding-tax percentage, and a rewritten collection posting: `Dr 1020 (BANK) [net] + Dr 1080 (WITHHOLDING_TAX_PREPAID, asset) [withholding amount] / Cr {company receivable} [full original claim amount]`, with any gap between the original claim amount and `approved_amount` posted to `Dr 5300 (BAD_DEBT)`. Partial approval reuses the existing `InsuranceClaim.approved`/`approved_amount` fields — no new claim status.

**Blocked by:** None (touches `onCollect()`, a different method than tickets 04–06)

**Status:** ready-for-agent

- [ ] `withholding_pct` (administrator-set, no invented default) added to `insurance_companies`.
- [ ] `UpdateInsuranceClaimAction` validates/populates `approved_amount` when a claim transitions to `approved`.
- [ ] `AutoPostInsuranceClaimAction::onCollect()` computes withholding from `approved_amount` (or claim amount if fully approved) × company `withholding_pct`, and posts the bank/withholding/bad-debt split described above; total debits == total credits.
- [ ] Test: full approval, no shortfall — bank + withholding only, no 5300 entry.
- [ ] Test: partial approval (approved_amount < claim amount) — bank + withholding + 5300 for the gap, matching the spec's worked example numbers.
- [ ] Test: 1080 is asserted as an asset-group account in every scenario.
- [ ] Existing `InsuranceClaimAccountingTest` collection assertions updated; any real cash-collection workflow that existing tests rely on is either preserved as an alternate branch or explicitly confirmed unused before removal.
