# 04: Doctor entitlement created on booking creation

**What to build:** When a receptionist creates a booking with نوع التعامل = شركة تأمين or تعاقد, the system automatically creates exactly one doctor entitlement linked to that booking. Its amount is the assigned doctor's fee for the booking's service — the `doctor_service` pivot fee, falling back to the service's default fee (`default_dr_fee`), and if neither resolves the booking still saves with a non-blocking notice and no entitlement. Normal (cash/card/transfer) bookings create nothing. No doctor or no service → no entitlement.

**Blocked by:** 01, 02, 03

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `doctor_entitlements` table: `id` (ULID), `booking_id` (unique, cascade on delete), `doctor_id`, `service_id` (nullable), `amount`, `source` (`insurance`|`contract`), `status` (`pending`|`settled`|`void`, default `pending`), `settled_at` (nullable), `dr_payment_id` (nullable), timestamps
- [ ] `DoctorEntitlement` model + `EntitlementSource` / `EntitlementStatus` enums (TitleCase keys); `Booking::doctorEntitlement()` HasOne
- [ ] `SyncDoctorEntitlementAction::execute(Booking)` — idempotent; resolves fee (pivot → `default_dr_fee` → none) and upserts the pending entitlement keyed by `booking_id`
- [ ] Wired into `CreateBookingAction` after the booking is created
- [ ] Insurance booking, doctor pivot fee 750 → one entitlement `amount=750`, `source=insurance`
- [ ] Contract booking → `source=contract`
- [ ] Service price 5000 + doctor fee 750 → entitlement is 750, not 5000
- [ ] No pivot fee, service `dr_share=600` → entitlement 600
- [ ] No pivot fee and `dr_share=0` → no entitlement, booking saved, flash notice present
- [ ] Insurance company omitted → entitlement still created
- [ ] Cash booking / no doctor / no service → no entitlement
- [ ] Unique `booking_id` prevents duplicates at the DB level
