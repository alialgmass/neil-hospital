# 06: Entitlement void on booking cancel / delete

**What to build:** Cancelling an insurance/contract booking voids its pending doctor entitlement. Deleting the booking removes its entitlement (DB cascade), except a settled entitlement, which is retained in a voided state so the payout history and audit trail survive.

**Blocked by:** 04

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `CancelBookingAction` voids the pending entitlement after the booking is cancelled
- [ ] Booking destroy path reverses/removes the entitlement; `booking_id` FK cascades on hard delete
- [ ] Cancel insurance booking → entitlement `void`
- [ ] Delete insurance booking → entitlement gone
- [ ] Existing `DestroyBookingTest` / cancel tests stay green

**Deferred to ticket 07:** retaining a *settled* entitlement (booking_id detach) on
hard delete — settlement linkage doesn't exist until the accounting/payout work.
Today `booking_id` cascades, so every entitlement of a deleted booking is removed.
