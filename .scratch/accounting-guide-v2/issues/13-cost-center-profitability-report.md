# 13: Cost Center Profitability Report screen

**What to build:** A report screen showing, per cost center and per period: total revenue, direct expenses, allocated shared expenses, net profit, and margin %, with a month-over-month comparison and a chart. The total row reconciles exactly to the income statement's net profit/loss for the period. CC-SURG and CC-INS stay separate so the two channels' margins are compared independently.

**Blocked by:** 02, 12

**Status:** ready-for-agent

- [ ] Reporting service groups `journal_entries` by `cost_center_id` for revenue and direct expense over a period
- [ ] Adds allocated shared expenses from the allocation-rules engine (ticket 12)
- [ ] Per center: revenue, direct expense, allocated shared expense, net profit, margin %
- [ ] Month-over-month comparison + chart
- [ ] Total row equals `IncomeStatementService` net profit/loss for the same period (test this invariant)
- [ ] New Inertia page under `resources/js/pages/` (prior art: `resources/js/pages/journal/CostCenters.vue`, `resources/js/pages/ledger/*`); Wayfinder route; permission-gated
- [ ] TDD at the reporting-service seam: build a period of entries across centers, assert per-center figures and the reconciliation invariant
- [ ] `vendor/bin/pint --dirty` clean; affected tests green; `npm run build` succeeds
