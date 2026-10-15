# 09: Shared-expense allocation-rule scaffold (schema only)

**What to build:** A minimal structure (table/config) for defining shared-expense allocation rules across cost centers (e.g. rent, admin salaries mapped to a set of cost centers with percentages), ready for an administrator to fill in later. No percentages are invented or defaulted by this ticket, and no posting action consumes the rules yet.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Schema/model exists for an allocation rule: a shared-expense account or category, a set of target cost centers, and a percentage per target.
- [ ] No seeded/default percentage rows are created.
- [ ] Basic CRUD (model + validation that percentages for a rule sum to 100%, or are individually capped at 100%, per whichever the team prefers) is covered by a test — no UI/screen required for this ticket.
- [ ] Explicitly not wired into any AutoPost action (documented as future work, out of scope here).
