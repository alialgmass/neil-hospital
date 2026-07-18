# Tasks: Fix Booking Flow

**Input**: Design documents from `/specs/002-fix-booking-flow/`
**Prerequisites**: plan.md ✅ · spec.md ✅ · research.md ✅ · data-model.md ✅ · contracts/ ✅

**Tests**: Included — required by constitution (every controller endpoint MUST have feature tests).

**Organization**: Tasks grouped by user story. Phase 2 (Foundational) MUST be complete before US1/US2 can be verified, since both stories share `BookingService` changes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to
- Paths are relative to repo root

---

## Phase 1: Setup (Verification)

**Purpose**: Confirm the test directory structure and locate any existing booking tests before writing new ones.

- [x] T001 Locate existing booking feature tests under `tests/Feature/` and note any files that cover `BookingController` or `UpdateBookingRequest` to avoid duplication

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: `BookingService` changes that are shared by User Stories 1 and 2. MUST be complete before those stories' controller and test tasks can be verified end-to-end.

**⚠️ CRITICAL**: US1 and US2 both depend on these tasks being complete.

- [x] T002 Add `getFormResources(): array` method to `Modules/Booking/Services/BookingService.php` — returns `['services' => Service::active()->select('id','name','dept','price','ins_price')->orderBy('name')->get(), 'insurance_companies' => InsuranceCompany::select('id','name')->orderBy('name')->get()]`
- [x] T003 Fix `BookingService::update()` in `Modules/Booking/Services/BookingService.php` — add `'status' => $data->status` to the update array and append pay_status auto-recalculation logic: `pay_status = paid >= net_due ? 'paid' : (paid > 0 ? 'partial' : 'unpaid')`

**Checkpoint**: `BookingService` changes done — US1 and US2 implementation can now proceed.

---

## Phase 3: User Story 1 — Booking Form Dropdowns (Priority: P1) 🎯 MVP

**Goal**: The "New Booking" and "Edit Booking" modals show populated service, doctor, and insurance company dropdowns.

**Independent Test**: Open `/booking`, click "حجز جديد", select department "clinic" → service dropdown shows clinic services, doctor dropdown shows active doctors.

### Feature Test for User Story 1

- [x] T004 [US1] Create `tests/Feature/Booking/BookingIndexTest.php` with tests: (a) `GET /booking` returns Inertia page with `services` prop containing active services, (b) returns `doctors` prop with active doctors only, (c) returns `insuranceCompanies` prop, (d) does NOT return `orRooms` prop

### Implementation for User Story 1

- [x] T005 [US1] Update `BookingController::index()` in `Modules/Booking/Controllers/BookingController.php` — call `$this->bookingService->getFormResources()`, query `Doctor::select('id','name')->where('is_active', true)->orderBy('name')->get()`, spread all three into the Inertia response, and REMOVE the `'orRooms' => $orRooms` key and its associated DB query
- [x] T006 [US1] Update `Props` interface in `resources/js/pages/booking/Index.vue` — add `services?: { id: string; name: string; dept: string; price: number; ins_price: number }[]`, `doctors?: { id: string; name: string; is_active: boolean }[]`, and `insuranceCompanies?: { id: string; name: string }[]`
- [x] T007 [US1] Run `php artisan test --compact tests/Feature/Booking/BookingIndexTest.php` and confirm all assertions pass

**Checkpoint**: New booking form dropdowns are populated. Test file passes independently.

---

## Phase 4: User Story 2 — Booking Status Save on Edit (Priority: P1)

**Goal**: Changing status in the edit modal persists correctly; pay_status auto-recalculates when amounts change.

**Independent Test**: Edit a "waiting" booking → set status to "confirmed" → save → booking row shows "confirmed" badge. Change paid_amount to match full price → save → pay_status badge shows "مسدد".

### Feature Test for User Story 2

- [x] T008 [US2] Create `tests/Feature/Booking/UpdateBookingTest.php` with tests: (a) `PUT /booking/{id}` with `status: 'confirmed'` persists the new status, (b) changing `paid_amount` to net_due auto-sets `pay_status` to `'paid'`, (c) changing `paid_amount` to 0 auto-sets `pay_status` to `'unpaid'`, (d) partial payment sets `pay_status` to `'partial'`

### Implementation for User Story 2

- [x] T009 [US2] Add `'status' => ['nullable', 'in:waiting,confirmed,in_progress,completed,cancelled']` to `rules()` in `Modules/Booking/Http/Requests/UpdateBookingRequest.php`
- [x] T010 [US2] Run `php artisan test --compact tests/Feature/Booking/UpdateBookingTest.php` and confirm all assertions pass

**Checkpoint**: Status changes and pay_status recalculation work end-to-end. Both P1 stories complete.

---

## Phase 5: User Story 3 — Department Pages Filter Within Their Own Dept (Priority: P2)

**Goal**: Status filter and pagination on `/lasik` stay on `/lasik`; same for `/laser` and `/surgery`.

**Independent Test**: Navigate to `/lasik`, select status filter "مجدولة" → URL stays `/lasik?status=scheduled`, results are lasik procedures only.

### Feature Test for User Story 3

- [x] T011 [P] [US3] Create `tests/Feature/Surgery/SurgeryIndexTest.php` with tests: (a) `GET /surgery` returns only `dept=surgery` procedures, (b) `GET /lasik` returns only `dept=lasik` procedures, (c) `GET /laser` returns only `dept=laser` procedures, (d) status filter on `/lasik` returns Inertia response without redirecting to `/surgery`

### Implementation for User Story 3

- [x] T012 [US3] Verify `applyFilters()` and `goToPage()` in `resources/js/pages/surgery/Index.vue`, `resources/js/pages/lasik/Index.vue`, and `resources/js/pages/laser/Index.vue` each use their own correct path (`/surgery`, `/lasik`, `/laser` respectively) — no code change expected but confirm and document
- [x] T013 [US3] Run `php artisan test --compact tests/Feature/Surgery/SurgeryIndexTest.php` and confirm all assertions pass

**Checkpoint**: Each department page is independently filterable and stays on its own route.

---

## Phase 6: User Story 4 — Date Range Filter Persists (Priority: P2)

**Goal**: After applying a date range filter, the filter inputs retain the selected dates on page reload.

**Independent Test**: Apply `date_from=2026-04-01&date_to=2026-04-15` → results update → page reload → `DateFilter` inputs still show "2026-04-01" to "2026-04-15".

### Feature Test for User Story 4

- [x] T014 [P] [US4] Create `tests/Feature/Booking/DateFilterTest.php` with tests: (a) `GET /booking?date_from=2026-04-01&date_to=2026-04-15` returns Inertia `filters` prop containing both `date_from` and `date_to`, (b) single `date` param is still echoed in `filters`, (c) results are scoped to the date range

### Implementation for User Story 4

- [x] T015 [US4] Update `request()->only([...])` in `BookingController::index()` in `Modules/Booking/Controllers/BookingController.php` to include `'date_from'` and `'date_to'` alongside the existing keys
- [x] T016 [US4] Run `php artisan test --compact tests/Feature/Booking/DateFilterTest.php` and confirm all assertions pass

**Checkpoint**: Date range filter state round-trips correctly through the Inertia page props.

---

## Phase 7: User Story 5 — Laser Page Props Fixed (Priority: P2)

**Goal**: The `/laser` schedule modal's booking dropdown lists eligible laser bookings instead of being a plain text input.

**Independent Test**: Navigate to `/laser`, click "جدولة ليزر" → modal opens with a `<select>` dropdown for booking that lists laser bookings with status "waiting" or "confirmed".

### Feature Test for User Story 5

- [x] T017 [P] [US5] Add test to `tests/Feature/Surgery/SurgeryIndexTest.php` — `GET /laser` returns Inertia `bookings` prop with laser-dept bookings in waiting/confirmed status (prop was previously `availableBeds`)

### Implementation for User Story 5

- [x] T018 [P] [US5] Fix `defineProps` in `resources/js/pages/laser/Index.vue` — remove `availableBeds: never[]`, add `bookings: { id: string; file_no: string; patient_name: string }[]`
- [x] T019 [US5] Replace the plain text `<input v-model="scheduleForm.booking_id" type="text">` in the schedule modal inside `resources/js/pages/laser/Index.vue` with a `<select v-model="scheduleForm.booking_id">` that iterates over `bookings` prop showing `file_no — patient_name` per option
- [x] T020 [US5] Run `php artisan test --compact tests/Feature/Surgery/SurgeryIndexTest.php` to confirm laser props test passes

**Checkpoint**: Laser page schedule form has a usable booking selector. All five user stories complete.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Code style, type safety, and full suite verification before marking the feature done.

- [x] T021 [P] Run `vendor/bin/pint --dirty --format agent` to fix PHP code style on all modified files
- [x] T022 [P] Run `npm run types:check` (TypeScript compilation) to confirm no type errors in `booking/Index.vue` and `laser/Index.vue`
- [x] T023 Run `php artisan test --compact` (full suite) to confirm no regressions across the entire application

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — BLOCKS US1 controller work and US2 test assertions
- **Phase 3 (US1)**: Depends on Phase 2 (needs `getFormResources()` in service)
- **Phase 4 (US2)**: Depends on Phase 2 (needs `update()` fix in service)
- **Phases 5, 6, 7 (US3/4/5)**: Independent of each other and of Phases 3/4; depend only on the existing codebase state
- **Phase 8 (Polish)**: Depends on all prior phases

### User Story Dependencies

- **US1 (P1)**: Needs Phase 2 complete (T002)
- **US2 (P1)**: Needs Phase 2 complete (T003)
- **US3 (P2)**: No Phase 2 dependency — can start immediately after Phase 1
- **US4 (P2)**: No Phase 2 dependency — can start immediately after Phase 1
- **US5 (P2)**: No Phase 2 dependency — can start immediately after Phase 1

### Within Each User Story

- Test task → Implementation tasks → Confirm test passes
- BookingService (Phase 2) → Controller → Vue Props (within US1)
- Request validation (T009) and Service (T003, Phase 2) both needed before UpdateBookingTest assertions pass

### Parallel Opportunities

- After Phase 2: T011 (US3 test), T014 (US4 test), T017 + T018 (US5) can all run in parallel
- T021 (Pint) and T022 (TypeScript) in Phase 8 can run in parallel
- T004 (US1 test) and T008 (US2 test) can be written in parallel immediately after Phase 1

---

## Parallel Example: Phase 2 → US3, US4, US5 in Parallel

```
After Phase 2 completes:

Stream A (US3): T011 → T012 → T013
Stream B (US4): T014 → T015 → T016
Stream C (US5): T017 + T018 [parallel] → T019 → T020
```

---

## Implementation Strategy

### MVP First (User Stories 1 & 2 — P1 only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (BookingService)
3. Complete Phase 3: US1 — form dropdowns
4. Complete Phase 4: US2 — status save
5. **STOP and VALIDATE**: Both P1 stories working — booking creation and editing fully functional
6. Run Phase 8 Polish tasks for P1 scope

### Incremental Delivery

1. Phases 1–2 → Foundation ready
2. Phase 3 → Form dropdowns fixed (MVP — most impactful P1 fix)
3. Phase 4 → Status save fixed (completes P1)
4. Phase 5 → Dept filter verified (P2)
5. Phase 6 → Date filter state (P2)
6. Phase 7 → Laser props (P2)
7. Phase 8 → Polish and full suite

---

## Notes

- No migrations needed — all fixes are code-only
- `[P]` tasks touch different files and have no shared dependencies
- Constitution requires feature tests for every controller endpoint — tests are not optional here
- Pay status recalculation (T003) must mirror the logic already in `PayBookingController` exactly
- After T005 (controller update), run `php artisan optimize:clear` if caching is active
