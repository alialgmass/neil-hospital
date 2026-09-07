# 10: Payroll gross + social-insurance share

**What to build:** Payroll recognizes the full labor cost. Approval posts gross salary (5210) and the hospital's social-insurance share (5211) as expenses, crediting net salary to the per-employee payable and the combined social-insurance amount to the social-insurance payable (2041). Salary payment clears the per-employee payable against the treasury. A remittance entry clears the social-insurance liability when the authority is paid.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] `AutoPostPayrollAction::onApprove`: Dr 5210 (gross) + Dr 5211 (hospital social-insurance share) / Cr per-employee net-salary payable (net) + Cr 2041 (employee + hospital social-insurance); all expense lines CC-ADMIN
- [ ] `onPay`: Dr per-employee payable / Cr 1010 + treasury outflow
- [ ] New remittance path: Dr 2041 / Cr 1010
- [ ] Accrual, payment, and remittance each idempotent
- [ ] TDD at the `AutoPostPayrollAction` seam (new `tests/Feature/HR/`): approve → assert 4 lines; pay → assert payable clears; remit → assert 2041 clears
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
