# 06: Insurance revenue by department + payer dimension

**What to build:** An insurance claim is tracked per payer and recognized as revenue by department on submission. Submission debits the correct per-payer receivable (1031–1047) and credits the department insurance revenue account (4110 clinic, 4120 labs, 4130 surgery, 4140 lasik, 4150 laser), both lines tagged with the payer dimension, at the full claim value on the service day. A fully rejected claim reverses that entry to the same accounts at its exact original amount.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] Payer → receivable-code mapping (1031–1047) resolvable from the claim's contracting party
- [ ] `AutoPostInsuranceClaimAction::onSubmit`: Dr per-payer receivable / Cr department insurance revenue; amount = full claim value; payer dimension on both lines; cost center from department (CC-INS for surgery/lasik insurance per the guide; clinic/labs/laser insurance keep their own center)
- [ ] `onApprove` still posts no journal entry
- [ ] `onReject` reverses `onSubmit` at the original amount against the per-payer receivable + department revenue account
- [ ] TDD at the `AutoPostInsuranceClaimAction` seam: extend `InsuranceClaimAccountingTest` — submit for each department + payer, reject, assert exact reversal
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
