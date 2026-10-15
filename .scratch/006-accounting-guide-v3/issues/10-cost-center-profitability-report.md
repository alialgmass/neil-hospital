# 10: Cost-center profitability report/query

**What to build:** A query/endpoint returning revenue, expenses, and net profit per cost center for a month, a year, or an arbitrary date range, built from the existing `journal_entries.cost_center` column and account `group` (revenue/expense) — no hardcoded account-code lists, no new schema.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Confirm whether a cost-center reporting query/endpoint already exists in `Modules/Reporting`; extend it if so, add a new one following its conventions if not.
- [ ] Query supports: a given month, a given year, and an arbitrary date range; returns revenue, expenses, and net profit per cost center; supports comparing centers.
- [ ] Query derives revenue/expense from account `group`, not a hardcoded account-code list.
- [ ] Test: a fixture with postings across at least 3 cost centers produces correct per-center revenue/expense/profit for a date range.
