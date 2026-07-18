# Research: Fix Booking Flow

**Feature**: 002-fix-booking-flow
**Date**: 2026-04-19
**Status**: Complete — no NEEDS CLARIFICATION items

---

## Decision 1: Where to load services/doctors/insuranceCompanies for BookingController

**Decision**: Add a `getFormResources(): array` method to `BookingService` that returns services and insurance companies (both in the Booking module). Doctor data is loaded with `Doctor::select('id', 'name')->where('is_active', true)->orderBy('name')->get()` called from the controller, consistent with the existing pattern in `SurgeryController`.

**Rationale**: Services and InsuranceCompany are Booking-module models — `BookingService` can query them without cross-module violation. The `Doctor` model lives in the Doctor module; the cleanest pattern that matches existing `SurgeryController` usage (which also queries Doctor directly) avoids introducing a new DoctorService interface purely for this read-only lookup.

**Alternatives considered**:
- Inject `DoctorService` via a new interface — adds interface + binding just for a two-field select; KISS principle says no.
- Move all three into `BookingService` — violates SRP (service shouldn't own cross-module queries).
- Direct queries in BookingController — violates Clean Architecture (controllers must not query DB).

**Constitution note**: Cross-module Model queries are PROHIBITED by the constitution. The Doctor query in the controller is a pragmatic deviation consistent with the existing `SurgeryController::index()` pattern, documented here per Complexity Tracking requirements. A future refactor could introduce a `DoctorRepositoryInterface` and service binding.

---

## Decision 2: How to add `status` to booking updates

**Decision**: Add `status` to `UpdateBookingRequest` rules (with `nullable`, `in:` validation), add it to `BookingService::update()`, and auto-recalculate `pay_status` in the same method based on `paid_amount` vs net amount.

**Rationale**: The request layer must validate all inputs (FR principle from constitution). Auto-recalculating `pay_status` from amounts prevents manual override inconsistencies (FR-008) and matches the logic already in `PayBookingController`.

**Alternatives considered**:
- Add a separate `UpdateBookingStatusAction` call from the controller — over-engineering for a combined edit form that already sends both status and amounts together.
- Keep status editable only through `BookingStatusController` — forces two separate requests for one user action.

---

## Decision 3: Fix date filter persistence

**Decision**: Extend `request()->only([...])` in `BookingController::index()` to include `date_from` and `date_to` alongside the existing `date` field. No changes to `BookingFilterData` or `BookingRepository` (they already handle both formats).

**Rationale**: `DateFilter.vue` sends `date_from`/`date_to`. The controller already reads them correctly via `BookingFilterData`, but it doesn't echo them back in the `filters` prop. Adding them to `only([...])` costs zero complexity and makes the state round-trip correctly.

**Alternatives considered**:
- Change `DateFilter.vue` to send a single `date` — breaks the range filter capability.
- Store filter state in session — unnecessary complexity.

---

## Decision 4: Fix laser/Index.vue props

**Decision**: Remove `availableBeds: never[]` from `laser/Index.vue` props and replace with `bookings: { id: string; file_no: string; patient_name: string }[]`. Update the schedule form's `booking_id` plain-text input to a `<select>` dropdown bound to `bookings`.

**Rationale**: The controller already sends `bookings`; the prop mismatch means the data is silently dropped. A dropdown is more usable and consistent with how `lasik/Index.vue` handles this.

**Alternatives considered**:
- Keep the text input — misses the improvement and leaves the prop mismatch.
- Remove `bookings` from the controller for laser — laser procedures should be linkable to bookings.

---

## Decision 5: Remove unused orRooms from BookingController::index()

**Decision**: Remove the `'orRooms' => $orRooms` key from the BookingController `index()` response. OR-room selection is handled within the department pages (surgery/lasik/laser), not the booking list.

**Rationale**: The `booking/Index.vue` Props interface doesn't declare `orRooms` and never uses it. Removing it reduces the Inertia payload and eliminates the unnecessary DB query.

**Alternatives considered**:
- Keep it for future use — YAGNI; add it back when needed.

---

## Summary Table

| # | Issue | Approach | Complexity |
|---|-------|----------|------------|
| 1 | Empty form dropdowns | `BookingService::getFormResources()` + doctor direct select in controller | Low |
| 2 | Status not saved on edit | Add to `UpdateBookingRequest` rules + `BookingService::update()` | Low |
| 3 | Pay status inconsistency | Auto-recalculate in `BookingService::update()` | Low |
| 4 | Date filter state lost | Add `date_from`/`date_to` to `filters` prop | Trivial |
| 5 | `laser/Index.vue` props wrong | Fix props type + upgrade text input to select | Low |
| 6 | Unused `orRooms` in booking index | Remove from controller response | Trivial |
