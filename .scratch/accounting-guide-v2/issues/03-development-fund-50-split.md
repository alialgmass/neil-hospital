# 03: Development fund (خزنة التطوير) — 50 ج split

**What to build:** Every cash service collection automatically moves 50 ج to the development-fund treasury (1011) and the remainder to the main treasury (1010), while still recognizing the full service revenue at its gross amount. The split happens once per booking (on the first payment), is tagged with the booking's cost center, is idempotent, and applies to every clinical department. Insurance-funded services are excluded. Spending from the fund is a normal treasury/journal outflow from 1011.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] `AutoPostBookingPaymentAction` posts, for a non-insurance booking's first payment: gross revenue credited in full; 50 ج line Dr 1011 / Cr 1010 tagged with the booking's cost center; net (paid − 50) reflected in the main treasury
- [ ] Idempotency key derived from the booking (`dev_fund:{file_no}`) — a retried/duplicate payment never double-transfers
- [ ] Applies across clinic, labs, laser, pentacam, surgery, lasik
- [ ] Insurance bookings post nothing here (already short-circuited — keep and test)
- [ ] Development-fund spend recordable as a treasury/journal outflow from 1011
- [ ] TDD at the `AutoPostBookingPaymentAction` seam: assert `journal_entries` + `accounts.balance` + `treasury_entries` for a cash clinic booking; second call is a no-op; insurance booking unaffected
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
