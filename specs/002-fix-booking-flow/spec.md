# Feature Specification: Fix Booking Flow

**Feature Branch**: `002-fix-booking-flow`
**Created**: 2026-04-19
**Status**: Draft
**Input**: User description: "fix booking flow"

## Overview

The booking flow is the central patient intake mechanism for Al-Nour Eye Hospital. A receptionist creates a booking, assigns it to a department (clinic, labs, surgery, lasik, laser), and the patient moves through that department's workflow. Several defects break this end-to-end flow: the booking creation form renders with empty dropdowns, status changes via the edit modal have no effect, the department-specific pages (laser/lasik) redirect incorrectly when filtering, and filter state is lost after date-range searches.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Receptionist Creates a New Booking with Services and Doctors (Priority: P1)

A receptionist opens the "New Booking" modal, selects the target department, picks a service from the service list and a doctor from the doctor list, fills in patient details, and saves. The booking appears in the list with the correct service, doctor, price, and status.

**Why this priority**: The booking creation form currently shows empty service and doctor dropdowns because the controller never loads them. No booking can be created correctly without this data.

**Independent Test**: Open `/booking`, click "حجز جديد", verify that service and doctor dropdowns are populated, submit the form, and confirm the new row appears in the table.

**Acceptance Scenarios**:

1. **Given** a receptionist is on `/booking`, **When** they open the "New Booking" modal and select the "clinic" department, **Then** the service dropdown shows only clinic services and the doctor dropdown shows all active doctors.
2. **Given** a receptionist selects a service, **When** the service is selected, **Then** the price field auto-populates with that service's standard price.
3. **Given** the form is filled with patient name, department, and visit date, **When** the form is submitted, **Then** the booking is saved and the new row appears in the list with a generated MRN.
4. **Given** the patient uses insurance, **When** the payment method is set to "insurance", **Then** the service price auto-populates with the insurance price and the insurance company field becomes visible.

---

### User Story 2 — Receptionist Changes Booking Status via Edit Modal (Priority: P1)

A receptionist opens the edit modal for an existing booking, changes its status (e.g., from "waiting" to "confirmed"), and saves. The list immediately reflects the new status.

**Why this priority**: The `BookingService::update()` method never writes the `status` field, so status changes through the edit form are silently lost. This breaks the patient tracking workflow.

**Independent Test**: Edit an existing "waiting" booking, set status to "confirmed", save, and verify the row badge changes to "confirmed".

**Acceptance Scenarios**:

1. **Given** a booking with status "waiting", **When** a user edits it and sets status to "confirmed" and saves, **Then** the booking status in the list updates to "confirmed".
2. **Given** a booking with status "completed", **When** the user opens the edit modal, **Then** status options that are invalid for "completed" (e.g., back to "waiting") are not selectable or are handled gracefully.
3. **Given** a booking edit is saved, **When** the payment amounts are changed, **Then** the pay status is recalculated to match the new paid/due amounts.

---

### User Story 3 — Department-Specific Pages Filter Within Their Own Department (Priority: P2)

A nurse on the `/lasik` page applies a status filter. The filtered results stay on `/lasik` and only show lasik procedures.

**Why this priority**: The Surgery, Lasik, and Laser pages share one controller but the filter/pagination actions hardcode `/surgery` as the URL, causing incorrect redirects when the user is on `/lasik` or `/laser`.

**Independent Test**: Navigate to `/lasik`, apply a status filter, verify the URL stays `/lasik` and results are scoped to lasik procedures.

**Acceptance Scenarios**:

1. **Given** a user is on `/lasik` and applies a status filter, **When** results load, **Then** the browser URL remains `/lasik` and only lasik entries are shown.
2. **Given** a user is on `/laser` and navigates to page 2, **When** the page loads, **Then** the URL remains `/laser` with the correct page parameter.
3. **Given** a user is on `/surgery`, **When** they filter by status "completed", **Then** only surgery entries with "completed" status appear.

---

### User Story 4 — Date Range Filter Persists After Application (Priority: P2)

A receptionist applies a date range filter on the booking list. After the page reloads, the filter inputs reflect the applied range and the results match the selected dates.

**Why this priority**: The controller returns `date` in the `filters` prop but the date filter component sends `date_from` and `date_to`. The mismatch means filter state is lost after the page reloads, confusing staff who cannot confirm which range is active.

**Independent Test**: Apply a date range filter, verify results update and filter inputs retain the selected dates on reload.

**Acceptance Scenarios**:

1. **Given** a receptionist applies a date range of "01/04/2026 to 15/04/2026", **When** the page loads, **Then** the date inputs still show the selected range and results are filtered to that period.
2. **Given** a date filter is active, **When** the user navigates to a different department tab, **Then** the date filter remains active.
3. **Given** a user clicks "clear" on the date filter, **When** the page loads, **Then** all dates are shown again.

---

### User Story 5 — Laser Page Receives Required Props (Priority: P2)

The `/laser` page displays the list of laser procedures and allows scheduling a new one. The schedule form's "booking" dropdown is populated with eligible laser bookings.

**Why this priority**: `laser/Index.vue` declares `availableBeds: never[]` in its props but the controller sends `bookings`. The page cannot reference bookings for the scheduling form, silently breaking the "Add Procedure" workflow.

**Independent Test**: Navigate to `/laser`, open the "Add Procedure" modal, verify the booking dropdown lists eligible laser-department bookings.

**Acceptance Scenarios**:

1. **Given** there are bookings with `dept = laser` in "waiting" or "confirmed" status, **When** the user opens the schedule form on `/laser`, **Then** those bookings appear in the booking dropdown.
2. **Given** the laser page loads, **When** the page renders, **Then** no console errors about undefined props or missing data appear.

---

### Edge Cases

- What happens if no services exist for a selected department? The service dropdown shows an empty state message rather than crashing.
- What happens if a booking's `paid_amount` exceeds the net price? The pay status must be capped at "paid" and the remainder shown as zero.
- What happens when a status transition is invalid (e.g., "completed" → "waiting")? The system rejects it with a clear Arabic error message.
- What if a booking already has a doctor assigned and the doctor list fails to load? The existing doctor name is still shown in the edit form.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The booking index controller MUST pass `services`, `doctors`, and `insuranceCompanies` to the booking page so form dropdowns are populated.
- **FR-002**: Services shown in the booking form MUST be filtered to match the selected department.
- **FR-003**: `BookingService::update()` MUST include `status` in the fields it persists.
- **FR-004**: The `SurgeryController::index()` MUST derive the current path dynamically so that filter and pagination links on `/lasik` and `/laser` navigate within their own route.
- **FR-005**: The booking index controller MUST include `date_from` and `date_to` in the `filters` prop returned to the page, in addition to or instead of the single `date` field.
- **FR-006**: `laser/Index.vue` props MUST be aligned with what `SurgeryController::index()` actually sends (`bookings`, `orRooms` or none, `inventoryItems` or none) based on what the laser workflow needs.
- **FR-007**: When a service is selected and the payment method is "insurance", the price field MUST auto-populate with the service's `ins_price`.
- **FR-008**: When `paid_amount` is updated via the edit form, the `pay_status` MUST be recalculated automatically (unpaid / partial / paid) based on the net amount due.

### Key Entities

- **Booking**: Central record linking patient, department, service, doctor, visit date, financial data (price, discount, insurance amount, paid amount, pay status), and workflow status.
- **Service**: A named procedure with a standard price and an insurance price, scoped to a department.
- **Doctor**: A clinician that can be assigned to a booking.
- **InsuranceCompany**: An insurer that covers part of the booking fee.
- **Surgery** (includes Lasik/Laser procedures): A procedure record linked to a booking, with its own lifecycle (scheduled → prep → in_progress → completed).

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A receptionist can complete a new booking with service and doctor selected in under 2 minutes from opening the modal.
- **SC-002**: 100% of status changes made through the edit modal are reflected immediately in the booking list without requiring a page reload.
- **SC-003**: Staff on `/lasik` or `/laser` never land on the `/surgery` URL when applying filters or paginating.
- **SC-004**: Date range filter values persist in the UI after page reload in 100% of cases.
- **SC-005**: The `/laser` scheduling form's booking dropdown populates with eligible bookings on every page load.
- **SC-006**: Pay status is always consistent with the actual paid and net amounts — no manual override can put a booking in "paid" status with a remaining balance.

---

## Assumptions

- Services, doctors, and insurance companies are already seeded in the database; this spec does not cover creating them.
- The `orRooms` prop passed to the booking index by the controller is not required by the booking page and can be removed from that response to reduce payload.
- Mobile / responsive layout changes are out of scope; fixes target desktop workflow only.
- The laser page does not need an OR-room grid view (unlike surgery); it only needs a list and the schedule form with bookings.
- Pay status recalculation on edit applies only to changes made via the edit modal, not the standalone "Pay" button (which already calculates correctly).
- The status machine (waiting → confirmed → in_progress → completed) is correct as defined; this spec does not change the allowed transitions.
