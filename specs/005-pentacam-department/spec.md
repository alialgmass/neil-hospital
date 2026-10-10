# Feature Specification: Finish Wiring the البنتكام (Pentacam) Department into Doctors & Booking

**Feature Branch**: `005-pentacam-department`
**Created**: 2026-09-11
**Status**: Draft — ready for agent
**Input**: User description: "add bantacan [البنتكام / Pentacam] department to add doctors and edit and show and reflect this to booking"

---

## Problem Statement

The البنتكام (Pentacam) department was added to the system backend (the `Department` enum, the `bookings`/`services` `dept` enum columns, the `SystemModule::Pentacam` toggle, revenue account 4090, and the doctor-dues exclusion in `AutoPostDoctorDuesAction`). The new-booking department picker (`DepartmentSelector.vue`) already lists it.

But the department was never finished on the two screens the user cares about:

1. **Adding / editing a doctor** — the doctor create/edit modal (`doctors/Index.vue`) builds its "الأقسام" checkboxes and its per-department fee-override cards from a **hardcoded array that omits `pentacam`**. So an administrator cannot scope a doctor to the Pentacam department or set a Pentacam per-department fee, even though the backend (`StoreDoctorRequest` / `UpdateDoctorRequest`, which validate against `Department::cases()`) would accept it.

2. **Showing a doctor** — because a doctor can never be assigned to Pentacam, the "الأقسام" column on the doctors list never shows it either.

3. **Booking** — the booking **edit** form (`BookingForm.vue`) has its **own** hardcoded `deptOptions` array that also omits `pentacam`. A booking routed to Pentacam therefore shows "—" as its department label in the form header and the confirmation summary, and the department is invisible when editing an existing Pentacam booking.

The root cause is that the list of departments is **copy-pasted in three places** (`DepartmentSelector.vue`, `BookingForm.vue`, `doctors/Index.vue`), each maintained by hand, and they have drifted. Every future department will hit the same bug.

## Solution

From the user's perspective:

1. **One canonical department list**, defined once on the server from the `Department` enum, filtered to the departments whose owning module is enabled, and delivered to the frontend as a shared Inertia prop. The three hardcoded arrays are deleted and all three components read the shared list.
2. **Adding a doctor**: the "الأقسام" checkboxes in the doctor create modal include **البنتكام**. Ticking it and saving scopes that doctor to the Pentacam department.
3. **Per-department fee**: the per-department fee-override section of the doctor form includes a **البنتكام** card, so a doctor can have a Pentacam-specific fee type and value like any other department.
4. **Editing a doctor**: opening an existing doctor shows البنتكام ticked when they are scoped to it, and its fee-override card pre-filled; unticking and saving removes the scope.
5. **Showing doctors**: the "الأقسام" column on the doctors list renders a **البنتكام** chip for doctors scoped to it.
6. **Booking — new**: unchanged (already works) — البنتكام remains one of the department buttons, shown only while the Pentacam module is enabled.
7. **Booking — edit**: opening a Pentacam booking shows **البنتكام** as the selected department in the form header and in the confirmation summary, not "—".
8. **Booking — doctor filter**: when البنتكام is the chosen department, the doctor dropdown shows doctors scoped to Pentacam plus unscoped ("كل الأقسام") doctors, exactly like every other department (this filter is already generic and needs no change — it is covered by tests only).
9. **Module toggle respected everywhere**: when the Pentacam module is disabled in settings, البنتكام disappears from all three screens (doctor form, doctor list filter context, booking pickers) the same way the other optional departments do.
10. **Doctor dues unchanged**: assigning a doctor to Pentacam and giving them a Pentacam fee does **not** cause a doctor-dues journal entry for Pentacam bookings — `AutoPostDoctorDuesAction` still returns early for `Department::Pentacam`. Pentacam stays centre-revenue-only.

---

## User Stories

### Canonical department list

1. As a developer, I want the list of selectable departments defined once on the server from the `Department` enum, so that adding the next department is a one-line change.
2. As a developer, I want that list to carry each department's value, its Arabic label, and its owning-module key, so that the frontend can render and module-filter it without a second source.
3. As a developer, I want the list filtered to departments whose owning `SystemModule` is enabled before it reaches the browser, so that disabled departments never appear in any picker.
4. As a developer, I want the three existing hardcoded department arrays (`DepartmentSelector.vue`, `BookingForm.vue`, `doctors/Index.vue`) removed and replaced by the shared list, so that they can never drift again.
5. As a developer, I want the shared list exposed as an Inertia prop available to both the doctors screen and the booking screen, so that neither controller has to assemble it ad hoc.

### Adding a doctor

6. As a clinic administrator, I want **البنتكام** to appear among the "الأقسام" checkboxes when I add a doctor, so that I can restrict a Pentacam technician/doctor to that department.
7. As a clinic administrator, when I tick البنتكام and save a new doctor, I want the doctor persisted with `pentacam` in their `departments`, so that the scope takes effect.
8. As a clinic administrator, when I add a doctor and tick البنتكام along with other departments, I want all ticked departments saved, so that multi-department doctors are supported.
9. As a clinic administrator, when I add a doctor and tick no departments at all, I want the doctor saved as "كل الأقسام" (unscoped) exactly as today, so that existing behaviour is preserved.
10. As a clinic administrator, I want a **البنتكام** card in the per-department fee-override section of the add-doctor form, so that I can set a Pentacam-specific fee type and value.
11. As a clinic administrator, when I enable the البنتكام fee-override card, set a fee type and value, and save, I want that override persisted in the doctor's `dept_fees` under the `pentacam` key, so that Pentacam fee logic can read it.
12. As a clinic administrator, when I leave the البنتكام fee-override card disabled, I want no `pentacam` entry written to `dept_fees`, so that the doctor falls back to their default fee for Pentacam.

### Editing a doctor

13. As a clinic administrator, when I open a doctor already scoped to البنتكام, I want its checkbox pre-ticked, so that I can see the current scope.
14. As a clinic administrator, when I open a doctor with a saved البنتكام fee override, I want its card pre-enabled and pre-filled with the saved fee type and value, so that I can review and adjust it.
15. As a clinic administrator, when I untick البنتكام on a doctor and save, I want `pentacam` removed from their `departments`, so that they stop being offered for Pentacam bookings.
16. As a clinic administrator, when I disable the البنتكام fee-override card and save, I want the `pentacam` key removed from `dept_fees`, so that the override is cleared.
17. As a clinic administrator, when I edit an unrelated field on a doctor who is scoped to البنتكام and save, I want their Pentacam scope and fee override left intact, so that saves are non-destructive.

### Showing doctors

18. As a clinic administrator, I want the "الأقسام" column on the doctors list to render a **البنتكام** chip for doctors scoped to it, so that I can see Pentacam staffing at a glance.
19. As a clinic administrator, I want doctors with no department scope to keep showing "كل الأقسام" in that column, so that the unscoped case stays clear.

### Booking

20. As a receptionist creating a booking, I want **البنتكام** to remain one of the "التوجيه إلى قسم" buttons, so that I can route a patient to Pentacam (unchanged, regression-guarded).
21. As a receptionist editing an existing Pentacam booking, I want the form header to show **البنتكام** as the current department, so that I know what I am editing.
22. As a receptionist editing a Pentacam booking, I want the confirmation summary to show **البنتكام** as the department, not "—", so that the summary is accurate.
23. As a receptionist, when البنتكام is the selected department, I want the doctor dropdown to list doctors scoped to Pentacam and unscoped doctors, so that I can pick the right person.
24. As a receptionist, when البنتكام is the selected department, I want the service dropdown filtered to `pentacam` services, so that I only pick valid services (already works via generic filter — regression-guarded).
25. As a receptionist, I want the Pentacam "بيانات فحص البنتكام" extra-details title to keep showing for Pentacam bookings, so that the existing Pentacam-specific form section is unaffected.

### Module toggle

26. As a system administrator, when I disable the البنتكام module in settings, I want البنتكام to disappear from the doctor add/edit form (both the checkboxes and the fee-override cards), so that a disabled department cannot be assigned.
27. As a system administrator, when I disable the البنتكام module, I want البنتكام to disappear from both booking department pickers, so that no new Pentacam bookings can be routed.
28. As a system administrator, when I re-enable the البنتكام module, I want البنتكام to reappear everywhere without any data migration, so that the toggle is reversible.
29. As a system administrator, when the البنتكام module is disabled, I want a doctor who was already scoped to `pentacam` to keep that value in the database untouched, so that re-enabling restores their scope.

### Accounting (guard rails — no behaviour change)

30. As an accountant, when a Pentacam booking is created for a doctor who now has a Pentacam scope and a Pentacam fee override, I want **no** doctor-dues journal entry posted, so that Pentacam stays centre-revenue-only as designed.
31. As an accountant, I want Pentacam revenue to keep posting to account 4090 exactly as it does today, so that this change is invisible in the ledger.

---

## Implementation Decisions

### The one seam: a server-provided department list

- Introduce a single provider for the selectable-department list. It derives from the `App\Enums\Department` enum for the value + Arabic label (`Department::label()`), and from `Modules\Admin\Enums\SystemModule` for the owning-module key and the enabled/disabled filter. `SystemModule::deptValue()` already maps a module to its `dept` string; the provider is its inverse plus the label.
- Shape per entry: `{ value: string, label: string, module: string }` (e.g. `{ value: 'pentacam', label: 'البنتكام', module: 'pentacam' }`). Departments whose owning module is **not** `SystemModule::Pentacam` etc. map to themselves; `clinic`/`labs`/`surgery`/`lasik`/`laser`/`pentacam` each have a 1:1 module.
- Deliver it as a **shared Inertia prop** from `App\Http\Middleware\HandleInertiaRequests`, named `departments`, sitting next to the existing `moduleStatus` prop. This keeps it in one place and makes it assertable in any feature test that hits an Inertia page. The list is already module-filtered server-side, so the frontend does not re-filter.
- Prior art for "enum → module-filtered list": `SystemModule::enabledDeptValues()` and `SystemModule::statuses()`.

### Frontend consumption

- `resources/js/pages/booking/Partials/DepartmentSelector.vue`: delete `allDeptOptions` and the local `moduleStatus` re-filter; read `departments` from `usePage().props`. Keep the existing icon/caption decoration by mapping `value → { icon, cap }` in a small local lookup (icons are presentational, not data).
- `resources/js/pages/booking/Partials/BookingForm.vue`: delete the local `deptOptions` array; read the shared `departments` prop. `selectedDeptLabel` and `deptExtraTitle` resolve against it. `pentacam` is included automatically, fixing the "—" label and the missing edit-form department.
- `resources/js/pages/doctors/Index.vue`: delete `allDepts` and the local `moduleStatus` re-filter; drive both the "الأقسام" checkboxes and the per-department fee-override cards from the shared `departments` prop. Replace the hardcoded `deptOverrides` reactive object (which enumerates `clinic/surgery/lasik/laser/labs`) with one keyed dynamically off the shared list, preserving the current default fee values per department where they exist and defaulting new departments (Pentacam) to a sensible default (fee type `percentage`, value `0`, disabled).
- The doctor-filter computed in `BookingForm.vue` (`filteredDoctors`) is already generic (`!d.departments || d.departments.length === 0 || d.departments.includes(form.dept)`) and needs **no change**.

### Backend — already done, verify only

- `App\Enums\Department::Pentacam` exists with label `البنتكام`. No enum change.
- `StoreDoctorRequest` / `UpdateDoctorRequest` validate `departments.*` and `dept_fees` keys against `Department::cases()` — Pentacam already passes. No request change.
- `Doctor` model casts `departments` and `dept_fees` to `array` and `worksInDept()` is department-agnostic. No model change.
- `Modules\Accounting\Actions\AutoPostDoctorDuesAction::execute` returns early on `$dept === Department::Pentacam`. **Must stay.** Add a regression test rather than touching it.
- `bookings.dept` / `services.dept` MySQL enums already include `pentacam` (migration `2026_09_01_000003_add_pentacam_to_departments`). No migration.

### No new migration, no new endpoint

- This is a frontend-wiring + shared-prop change. `doctors.store` / `doctors.update` and the booking endpoints already accept everything needed.
- Wayfinder regen only if a route signature changes (it does not).

### Modules touched

- `app/Http/Middleware/HandleInertiaRequests.php`: add the `departments` shared prop.
- New provider (a small class or a static method on an existing enum/service — implementer's choice, but one place): produces the `{value,label,module}` list, module-filtered. Natural home: a static method on `App\Enums\Department` (e.g. `Department::optionsForEnabledModules()`) or a tiny `DepartmentOptionsService`. Keep it where `SystemModule` can be imported without a circular dependency.
- Frontend: `DepartmentSelector.vue`, `BookingForm.vue`, `doctors/Index.vue`.
- No controller changes required if the prop is shared middleware-level; if the team prefers per-controller props instead, `DoctorController::index` and `BookingController::index` each gain `'departments' => ...`.

### Domain glossary

- البنتكام = Pentacam = `Department::Pentacam` = `dept` value `pentacam` = `SystemModule::Pentacam`.
- الأقسام (on the doctor form) = `doctors.departments` (JSON array of `dept` values; empty/null = "كل الأقسام" = works everywhere).
- per-department fee override = `doctors.dept_fees` (JSON keyed by `dept` value → `{fee_type, fee_value}`).
- التوجيه إلى قسم = the booking `dept` field.
- موديول / module toggle = `SystemModule` + `Setting` row, surfaced as the `moduleStatus` Inertia prop.

---

## Testing Decisions

**What makes a good test here**: assert what the user or an integrator observes — the Inertia `departments` prop contains an entry with `value: 'pentacam'` / omits it when the module is off; a `POST /doctors` with `departments: ['pentacam']` persists that array; a `PUT /doctors/{id}` unticking it removes it; a Pentacam booking's Inertia payload carries `dept: 'pentacam'`; no journal row is written for a Pentacam booking's doctor dues. Do **not** assert on Vue component internals, the shape of the local icon lookup, or which file the provider lives in.

**Primary seam — feature tests on existing Inertia endpoints** (`GET /doctors`, `POST /doctors`, `PUT /doctors/{id}`, `GET /booking`, `PUT /booking/{id}`):

- Prior art: `tests/Feature/Doctor/DoctorDepartmentsTest.php` (extend this file), `tests/Feature/Admin/SystemModuleDeptFilteringTest.php`, `tests/Feature/Admin/SystemModuleToggleTest.php`, `tests/Feature/DoctorDeptFeesTest.php`.
- Scenarios:
  1. `GET /doctors` → Inertia `departments` prop includes `{ value: 'pentacam', label: 'البنتكام' }`.
  2. `GET /booking` → same `departments` prop includes the pentacam entry.
  3. Pentacam module disabled (`SystemModule::Pentacam` off) → `departments` prop on both pages **omits** `pentacam`; other departments still present.
  4. `POST /doctors` with `departments: ['pentacam']` → doctor persisted with `departments === ['pentacam']`.
  5. `POST /doctors` with `departments: ['clinic','pentacam']` and a `dept_fees.pentacam` override → both persisted; `dept_fees['pentacam']` has the given `fee_type`/`fee_value`.
  6. `POST /doctors` with no `departments` → `departments` is null/empty (unscoped), unchanged behaviour.
  7. `PUT /doctors/{id}` removing `pentacam` from a scoped doctor → `departments` no longer contains it; other departments retained.
  8. `PUT /doctors/{id}` disabling the pentacam fee override → `dept_fees` no longer has the `pentacam` key.
  9. `PUT /doctors/{id}` editing an unrelated field on a pentacam-scoped doctor → scope + override intact.
  10. Doctor scoped to `['pentacam']` + a generic unscoped doctor → `GET /booking` `doctors` prop exposes `departments` for client filtering (mirror the existing `test_booking_form_resources_expose_doctor_departments_for_client_side_filtering`).
  11. Pentacam module disabled → a doctor already scoped to `['pentacam']` keeps that value after a `PUT` that doesn't touch departments (no silent scrub).

**Accounting regression — feature test** (prior art: `tests/Feature/Booking/DoctorEntitlementAccountingTest.php`, `PayBookingAccountingTest`):

  12. Create a Pentacam booking with a doctor who has a `pentacam` scope and a `dept_fees.pentacam` override, take a payment → **no** doctor-dues journal entry (Dr 5110/5120 / Cr 2010) is posted for it; Pentacam revenue still posts to 4090.

**Frontend**: no component unit tests exist in this project for these pages; coverage is via the Inertia-prop feature tests above. If the team adds Vitest later, a `DepartmentSelector` test asserting it renders exactly the departments in the prop would be the right shape — out of scope here.

**Regression**: run `tests/Feature/Doctor`, `tests/Feature/Booking`, and `tests/Feature/Admin` directories plus `--filter=Department` and `--filter=SystemModule` after the change, then the full suite (`php artisan test --compact`).

---

## Out of Scope

- Any Pentacam-specific **fee calculation** — this spec only lets an administrator *record* a Pentacam scope and a Pentacam `dept_fees` entry. Whether anything reads `dept_fees['pentacam']` for a claim is unchanged (and today nothing does, because `AutoPostDoctorDuesAction` skips Pentacam).
- Changing the Pentacam **doctor-dues exclusion** — Pentacam stays centre-revenue-only; the early return in `AutoPostDoctorDuesAction` is deliberately preserved and only regression-tested.
- The Pentacam **revenue account (4090)**, the `services`/`bookings` enum widening, and the `SystemModule::Pentacam` toggle itself — all already shipped.
- The "بيانات فحص البنتكام" Pentacam booking sub-section content — unchanged.
- Adding icons/captions for Pentacam beyond a presentational default in the department picker.
- Retrofitting a shared department list into unrelated screens that also hardcode departments (`hr/Employees.vue`, `inventory/StockIssue.vue`, `inventory/StockPermit.vue`, `admin/Settings.vue`) — worth doing later, but each has different semantics and is not needed to satisfy this request. Note it in Further Notes.
- Any new database migration, model, or HTTP endpoint.

---

## Further Notes

- **Why the shared-prop seam instead of just adding `'pentacam'` to two arrays**: the department list is copy-pasted in at least three booking/doctor components and they have already drifted once (`DepartmentSelector.vue` has Pentacam, the other two don't). Patching the arrays fixes today's bug and guarantees the same bug for department #7. One server-derived list from `Department` + `SystemModule` removes the class of bug and is directly testable.
- **Other hardcoded department lists** exist in `hr/Employees.vue`, `inventory/StockIssue.vue`, `inventory/StockPermit.vue`, and `admin/Settings.vue`. They use department strings for different purposes (HR staff department, stock-issue cost attribution) and are intentionally left alone here; a follow-up spec could migrate them to the same shared prop.
- **Provider placement**: `App\Enums\Department` importing `Modules\Admin\Enums\SystemModule` is the cleaner direction (app → module is already how `HandleInertiaRequests` works). If a circular-dependency concern arises, put the list-builder in a small `App\Support` / `Modules\Admin` service instead. The spec fixes the *contract* (`{value,label,module}`, module-filtered, shared prop `departments`), not the file.
- **Icons**: `DepartmentSelector.vue` currently decorates each department with an emoji + caption. Those are presentational and can stay as a `value → {icon,cap}` map in the component; the data (value, label, enabled) comes from the prop. Pentacam already has `📷` / `فحص القرنية` in `DepartmentSelector.vue` — reuse it.
- **`deptOverrides` in `doctors/Index.vue`** currently hardcodes default percentages per department (clinic 40, surgery 60, lasik 60, laser 35, labs 30). Preserve those as a lookup keyed by department value; Pentacam (and any future department not in the lookup) defaults to `{ enabled: false, fee_type: 'percentage', fee_value: 0 }`.
- **Issue tracker**: no issue tracker / triage-label vocabulary is configured for this project, so this spec is filed as `specs/005-pentacam-department/spec.md` following the existing spec-kit convention (`001`–`004`). Run `/setup-matt-pocock-skills` to wire up a real tracker if you want `to-spec` to publish issues directly and apply the `ready-for-agent` label.
