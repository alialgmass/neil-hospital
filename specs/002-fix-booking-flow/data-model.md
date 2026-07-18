# Data Model: Fix Booking Flow

**Feature**: 002-fix-booking-flow
**Date**: 2026-04-19

> No schema migrations are required for this feature. All fixes are code-only changes to existing models, services, and frontend components.

---

## Affected Entities

### Booking

Existing table. No column additions or removals.

**Relevant fields for this fix**:

| Field | Type | Notes |
|-------|------|-------|
| `status` | `string` | `waiting` \| `confirmed` \| `in_progress` \| `completed` \| `cancelled` — currently excluded from update path |
| `paid_amount` | `decimal(10,2)` | Updated via edit modal and Pay button |
| `pay_status` | `string` | `unpaid` \| `partial` \| `paid` — must be auto-derived from amounts |
| `price` | `decimal(10,2)` | Service price |
| `discount` | `decimal(10,2)` | Applied discount |
| `ins_amount` | `decimal(10,2)` | Insurance portion |

**Pay status derivation rule** (enforced in `BookingService::update()`):
```
net_due = max(0, price - discount - ins_amount)
pay_status = paid_amount >= net_due ? 'paid'
           : paid_amount > 0        ? 'partial'
           : 'unpaid'
```

**Status transition machine** (unchanged — enforced by `UpdateBookingStatusAction`):
```
waiting → confirmed | cancelled
confirmed → in_progress | cancelled
in_progress → completed | cancelled
completed → (terminal)
cancelled → (terminal)
```

Note: the edit modal allows free status selection; the state machine is only enforced on the `PATCH /{id}/status` endpoint. The edit form (`PUT /{id}`) accepts any valid status value — this matches the existing design intent.

---

### Service (Booking module)

Existing table. No changes.

**Relevant fields exposed to booking form**:

| Field | Type | Notes |
|-------|------|-------|
| `id` | `ulid` | — |
| `name` | `string` | Displayed in dropdown |
| `dept` | `string` | Used to filter services by selected department |
| `price` | `decimal(10,2)` | Auto-populated when service selected (standard) |
| `ins_price` | `decimal(10,2)` | Auto-populated when payment method is "insurance" |
| `status` | `string` | Only `active` services shown (`scopeActive`) |

---

### Doctor (Doctor module)

Existing table. No changes.

**Relevant fields exposed to booking form**:

| Field | Type | Notes |
|-------|------|-------|
| `id` | `ulid` | — |
| `name` | `string` | Displayed in dropdown |
| `is_active` | `boolean` | Only active doctors shown |

---

### InsuranceCompany (Booking module)

Existing table. No changes.

| Field | Type | Notes |
|-------|------|-------|
| `id` | `ulid` | — |
| `name` | `string` | Displayed in dropdown |

---

## No New Tables or Migrations

All defects are in the service layer (missing field in update), the controller layer (missing data in page response), and the frontend layer (wrong prop types). No database schema changes are needed.
