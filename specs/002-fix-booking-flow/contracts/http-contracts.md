# HTTP Contracts: Fix Booking Flow

**Feature**: 002-fix-booking-flow
**Date**: 2026-04-19

These contracts describe the **before → after** shape of each modified Inertia endpoint.

---

## GET /booking — Booking Index

### Before (broken)
```json
{
  "bookings": { "data": [...], "current_page": 1, "last_page": 1, "total": 0 },
  "filters":  { "date": null, "dept": null, "status": null, "pay_status": null, "search": null },
  "todayStats": { "clinic": 0, "surgery": 0, "lasik": 0, "laser": 0, "labs": 0 },
  "orRooms":  [...]
}
```
Issues: `services`, `doctors`, `insuranceCompanies` absent → empty dropdowns. `date_from`/`date_to` absent → filter state lost. `orRooms` present but unused.

### After (fixed)
```json
{
  "bookings": { "data": [...], "current_page": 1, "last_page": 1, "total": 0 },
  "filters":  { "date": null, "date_from": null, "date_to": null, "dept": null, "status": null, "pay_status": null, "search": null },
  "todayStats": { "clinic": 0, "surgery": 0, "lasik": 0, "laser": 0, "labs": 0 },
  "services": [
    { "id": "01j...", "name": "فحص عام", "dept": "clinic", "price": "150.00", "ins_price": "100.00" }
  ],
  "doctors": [
    { "id": "01j...", "name": "د. أحمد", "is_active": true }
  ],
  "insuranceCompanies": [
    { "id": "01j...", "name": "شركة التأمين الوطنية" }
  ]
}
```
Changes: Added `services`, `doctors`, `insuranceCompanies`. Added `date_from`, `date_to` to `filters`. Removed `orRooms`.

---

## PUT /booking/{id} — Update Booking

### Request — Before (broken)
```json
{
  "patient_name": "محمد علي",
  "dept": "clinic",
  "visit_date": "2026-04-20",
  "doctor_id": "01j...",
  "service_id": "01j...",
  "price": 150,
  "discount": 0,
  "ins_amount": 0,
  "paid_amount": 150,
  "pay_method": "cash",
  "pay_status": "paid",
  "visit_note": ""
}
```
Issues: `status` field is ignored (not in `UpdateBookingRequest` rules, not in `BookingService::update()`). `pay_status` is accepted as-is without recalculation.

### Request — After (fixed)
```json
{
  "patient_name": "محمد علي",
  "dept": "clinic",
  "visit_date": "2026-04-20",
  "status": "confirmed",
  "doctor_id": "01j...",
  "service_id": "01j...",
  "price": 150,
  "discount": 0,
  "ins_amount": 0,
  "paid_amount": 150,
  "pay_method": "cash",
  "pay_status": "paid",
  "visit_note": ""
}
```
New `UpdateBookingRequest` rule for `status`:
```php
'status' => ['nullable', 'in:waiting,confirmed,in_progress,completed,cancelled'],
```
`BookingService::update()` now includes `status` in the update payload and auto-recalculates `pay_status`.

---

## GET /laser — Laser Index (Inertia page props)

### Before (broken)
Controller sends `bookings`, `orRooms`, `inventoryItems`, `doctors`, `surgeries`, `dept`, `filters`.
`laser/Index.vue` declares `availableBeds: never[]` — receives undefined for bookings.

### After (fixed)
`laser/Index.vue` props updated:
```typescript
defineProps<{
    surgeries: Paginator;
    bookings: { id: string; file_no: string; patient_name: string }[];
    doctors: { id: string; name: string }[];
    dept: string;
    filters: { status?: string };
}>()
```
`orRooms` and `inventoryItems` are also passed by the controller but not needed by the laser workflow — they can be ignored (unused but harmless) or the controller can be scoped to omit them for laser. Recommendation: keep controller unchanged, just fix the Vue props.

The schedule modal's `booking_id` input upgrades from plain text to a `<select>` bound to `bookings`.

---

## No New Routes

No routes are added, removed, or renamed. All changes are to the request validation rules, service method payloads, controller response shapes, and Vue component prop types.
