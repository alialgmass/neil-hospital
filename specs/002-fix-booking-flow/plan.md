# Implementation Plan: Fix Booking Flow

**Branch**: `master` | **Date**: 2026-04-19 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/002-fix-booking-flow/spec.md`

## Summary

Five defects in the hospital booking flow are patched with targeted, code-only fixes: the booking creation form receives missing lookup data (services, doctors, insurance companies); the edit modal's status field is wired through validation and the service layer; date range filter state is preserved in the Inertia response; the laser page's props are corrected and the booking selector upgraded to a dropdown; and an unused `orRooms` payload is removed from the booking index.

## Technical Context

**Language/Version**: PHP 8.4 · TypeScript 5.x
**Primary Dependencies**: Laravel 13 · Vue 3 · Inertia.js v3 · spatie/laravel-permission · PHPUnit 12
**Storage**: MySQL (via Eloquent · repository pattern)
**Testing**: PHPUnit 12 · `php artisan test --compact`
**Target Platform**: Web (Laravel Herd · local dev)
**Project Type**: Web application (Laravel + Inertia SPA)
**Performance Goals**: No regressions; no new N+1 queries introduced
**Constraints**: No schema migrations; no new dependencies; SOLID + Clean Architecture enforced
**Scale/Scope**: Single-hospital deployment; booking volume ~50–200/day

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| SRP | ✅ PASS | Each class retains its single responsibility. `BookingService::getFormResources()` is new but cohesive with the booking context. |
| OCP | ✅ PASS | No existing interfaces modified; only implementations updated. |
| LSP | ✅ PASS | No new subtypes introduced. |
| ISP | ✅ PASS | No interface changes. |
| DIP | ⚠ TRACKED | `BookingController` will call `Doctor::select()` directly (cross-module Model). Documented below in Complexity Tracking. Consistent with existing `SurgeryController` pattern. |
| KISS | ✅ PASS | All fixes are minimal; no new abstractions introduced. |
| DRY | ✅ PASS | Pay status recalculation logic consolidated in `BookingService::update()`, matching existing `PayBookingController` logic. |
| Clean Architecture | ⚠ TRACKED | Direct Doctor model access from controller. See Complexity Tracking. |
| Test Discipline | ✅ PASS | Feature tests updated for each modified endpoint. |

## Project Structure

### Documentation (this feature)

```text
specs/002-fix-booking-flow/
├── plan.md              ← this file
├── spec.md
├── research.md
├── data-model.md
├── contracts/
│   └── http-contracts.md
├── checklists/
│   └── requirements.md
└── tasks.md             ← created by /speckit.tasks
```

### Source Code (files touched)

```text
Modules/Booking/
├── Controllers/
│   └── BookingController.php          ← add form resources to index(); remove orRooms
├── Http/Requests/
│   └── UpdateBookingRequest.php       ← add status validation rule
├── Services/
│   └── BookingService.php             ← getFormResources(); update() includes status + pay_status recalc
├── DTOs/
│   └── BookingData.php                ← ensure status flows from array (already present)

resources/js/pages/
├── booking/
│   └── Index.vue                      ← add services/doctors/insuranceCompanies/date_from/date_to to Props
└── laser/
    └── Index.vue                      ← fix props (bookings replaces availableBeds); upgrade booking select

tests/Feature/Booking/
├── BookingIndexTest.php               ← assert services/doctors/insuranceCompanies in props
├── UpdateBookingTest.php              ← assert status persists; assert pay_status recalculated
└── DateFilterTest.php                 ← assert date_from/date_to echoed in filters
```

**Structure Decision**: Single-module Web Application. No new directories. All changes are in-place within the existing Booking module and frontend pages.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| `BookingController` queries `Doctor` model directly (cross-module) | Booking form needs active doctor list; no DoctorService interface exists | Introducing a `DoctorRepositoryInterface` and service-container binding is disproportionate for a two-field read-only select on one endpoint. Consistent with existing `SurgeryController::index()` pattern. Will be addressed in a dedicated Doctor module refactor. |

## Implementation Order

Changes are independent and can be applied in any order, but the sequence below minimises risk:

1. **`BookingService`** — add `getFormResources()`, fix `update()` (status + pay_status)
2. **`UpdateBookingRequest`** — add status rule
3. **`BookingController`** — wire `getFormResources()`, fix `filters`, remove `orRooms`
4. **`booking/Index.vue`** — add Props entries
5. **`laser/Index.vue`** — fix props, upgrade booking select
6. **Feature tests** — one test class per changed endpoint

## Phase 0: Research

✅ Complete — see [research.md](research.md). No NEEDS CLARIFICATION items remained. All six decisions documented.

## Phase 1: Design

✅ Complete — artifacts:
- [data-model.md](data-model.md) — no migrations needed; pay status derivation rule documented
- [contracts/http-contracts.md](contracts/http-contracts.md) — before/after shapes for GET /booking and PUT /booking/{id} and GET /laser

### Post-Design Constitution Re-check

All gates pass. One documented deviation (cross-module Doctor query) is justified by KISS and consistency with the existing codebase, tracked in Complexity Tracking above.
