# 04: Insurance claim submission — department revenue + company receivable routing

**What to build:** Submitting an insurance claim posts revenue to the correct department account (4110 Clinic, 4120 Lab, 4130 Surgery, 4140 Lasik, 4150 Laser) instead of always 4110, and posts the receivable to the specific insurance company's own sub-account (1031–1047) instead of always the aggregate 1030. 1030 stays a non-postable roll-up for reporting only.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Accounts 1031–1047 exist (seeded), one per contracted insurance company, under parent 1030, non-postable-parent-safe.
- [ ] `insurance_companies` (both `Modules/Booking/Models/InsuranceCompany.php` and `Modules/Insurance/Models/InsuranceCompany.php`, same table) can resolve its own receivable account.
- [ ] `AutoPostInsuranceClaimAction::onSubmit()` resolves the credit account from the claim's department (reusing the existing department-resolution pattern `AccountCode` already uses for cash postings) and the debit account from the claim's company.
- [ ] Test: claims for Clinic/Lab/Surgery/Lasik/Laser each post to their correct revenue account.
- [ ] Test: claims for two different companies post to two different receivable accounts, each with its own running balance.
- [ ] Existing `InsuranceClaimAccountingTest` submission assertions updated accordingly; total debits == total credits holds.
