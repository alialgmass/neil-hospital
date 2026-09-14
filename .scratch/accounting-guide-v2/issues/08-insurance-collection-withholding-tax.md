# 08: Insurance collection + withholding tax (1080)

**What to build:** When an insurance claim is collected, the payer transfers the money to the bank net of tax withheld at source and remits that tax to the authority. The collection debits bank (1020) for the transferred amount, debits prepaid withholding tax (1080) for the tax withheld, and credits the per-payer receivable for the full original amount — so the receivable closes exactly and the withholding is preserved as a recoverable asset, never treated as bad debt. A partially-approved claim collects bank + withholding for the approved portion and writes the disallowed difference off to bad debt (5300); the receivable still closes to zero.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] `AutoPostInsuranceClaimAction::onCollect`: Dr 1020 (transferred) + Dr 1080 (withheld) / Cr per-payer receivable (full original amount)
- [ ] Withholding amount from a configurable rate or an explicit entered value on the collection
- [ ] Partial approval: Dr 1020 + Dr 1080 for the approved portion, Dr 5300 for the disallowed difference, Cr per-payer receivable in full
- [ ] Withholding and bad debt are always distinct lines
- [ ] Year-end 1080 ↔ 2070 settlement is a documented manual journal; both accounts exist and are postable (no automation here)
- [ ] TDD at the `onCollect` seam: full collection with withholding; partial approval; assert receivable closes to zero in every case
- [ ] Guide worked example reproduces: collect 6,500 ج claim → 1020 +6,000, 1080 +500, 1031 closes
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
