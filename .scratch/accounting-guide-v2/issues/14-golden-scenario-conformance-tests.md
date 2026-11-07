# 14: Golden-scenario conformance tests + trial-balance invariant

**What to build:** An end-to-end test class replays الدليل المحاسبى's three worked examples through the real AutoPost/Process actions, event by event, and asserts the guide's exact figures. Plus a standing invariant that total debits always equal total credits.

**Blocked by:** 03, 04, 05, 06, 07, 08, 10, 11

**Status:** ready-for-agent

- [ ] `tests/Feature/Accounting/GuideScenariosTest.php`
- [ ] Scenario 1 — 6,500 ج cataract insurance case: assert final per-account balances (1031 opens+closes at 6,500; 4130 +6,500; 5010 +2,000; 5130 +500; 1010 −500; 1020 +6,000; 1080 +500; 2010 untouched)
- [ ] Scenario 2 — full May-2026 month: assert trial balance 189,870 / 189,870, income statement net loss 5,197, balance sheet 144,803
- [ ] Scenario 3 — 20 cases per department: assert trial balance 823,400 / 823,400, net profit 207,800, and the cost-center profitability table
- [ ] Where the guide's prose arithmetic contradicts the stated rule (base is always `paid − 50`), the rule wins; tests encode the rule-consistent numbers
- [ ] Standing invariant helper: after any scenario, `SUM(debits) == SUM(credits)` across `journal_entries` and Σ debit-nature balances == Σ credit-nature balances
- [ ] Full test suite green (`php artisan test --compact`)
