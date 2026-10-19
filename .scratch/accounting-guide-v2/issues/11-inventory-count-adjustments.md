# 11: Inventory count adjustments

**What to build:** When a physical inventory count differs from the book balance, the difference is posted. A shortage (book > physical) is Dr 5010 (cost of supplies — shortage) / Cr 1050. A surplus (physical > book) is Dr 1050 / Cr 4220 (miscellaneous revenue). Triggered from the count / reconciliation flow.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] Count shortage posts Dr 5010 / Cr 1050 for the difference and brings inventory to the physical count
- [ ] Count surplus posts Dr 1050 / Cr 4220 for the difference and brings inventory to the physical count
- [ ] Cost center from the department being counted, else CC-INV/CC-ADMIN
- [ ] Idempotent per count
- [ ] TDD at the action seam (prior art: `AutoPostStockIssueActionTest`): shortage case, surplus case, assert `journal_entries` + `accounts.balance` + inventory quantity
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
