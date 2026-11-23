# 12: Shared-expense allocation rules

**What to build:** A configurable allocation-rules table distributes shared expenses (rent, admin salaries, utilities) across the revenue-generating cost centers by defined ratios, so a fully-absorbed center profitability view can be produced. Shared expenses stay off the centers in the raw ledger — allocation is applied at report time.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Allocation-rules table: source (expense account or category), target cost center, ratio; ratios per source sum to 1
- [ ] Report-time allocation function: given a period, returns each center's allocated shared-expense amount
- [ ] Raw `journal_entries` for salaries/utilities/rent remain untagged by center (they show in the total row)
- [ ] Seedable default rule set
- [ ] TDD: given rules + a period of shared-expense entries, assert each center's allocated amount and that the allocations sum to the total shared expense
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
