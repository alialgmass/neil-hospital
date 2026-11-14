# 01: `تعاقد` deal type

**What to build:** A receptionist can create or edit a booking whose نوع التعامل is **تعاقد** (contract), alongside the existing نقدي / بطاقة / تحويل / تأمين options. The booking saves, appears in the list, and can be paid exactly like the other methods. No doctor-entitlement behaviour yet — this ticket only makes `contract` a first-class payment method.

**Blocked by:** None (can start immediately)

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `PayMethod` enum has a `Contract = 'contract'` case with the Arabic label `تعاقد`
- [ ] Every `in:cash,card,transfer,insurance` validation rule (store booking, update booking, pay booking) accepts `contract`
- [ ] Every `match` over `PayMethod` handles the new case (no unhandled-match errors)
- [ ] The booking form deal-type selector offers `تعاقد`
- [ ] Creating and editing a booking with `pay_method = contract` succeeds and round-trips
- [ ] Existing booking / payment feature tests stay green
