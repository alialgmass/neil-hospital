---
description: "Task list for Al-Nour Eye Hospital Management System"
---

# Tasks: Al-Nour Eye Hospital Management System (001-eye-hospital-hms)

**Input**: Design documents from `specs/001-eye-hospital-hms/`
**Prerequisites**: plan.md ✅ · spec.md ✅ · research.md ✅ · data-model.md ✅ · contracts/ ✅ · quickstart.md ✅

**Tests**: Not included by default (not explicitly requested in spec). Add PHPUnit tests per module after core implementation or run `/speckit.tasks` with TDD flag.

**Organization**: Tasks grouped by user story for independent implementation and delivery.

---

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1–US9)
- Include exact file paths

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Install dependencies, scaffold all 12 modules, configure global plumbing.

- [x] T001 Add `maatwebsite/laravel-excel` to `composer.json` and run `composer require maatwebsite/laravel-excel`
- [x] T002 [P] Scaffold 12 nwidart modules: `php artisan module:make Booking Clinic Labs Surgery Lasik Laser Doctor Accounting Inventory Insurance Reporting Admin`
- [x] T003 [P] Create `app/Repositories/BaseRepository.php` abstract class with `paginate()`, `findOrFail()`, `create()`, `update()`, `delete()` stubs
- [x] T004 [P] Create `app/DTOs/PatientDTO.php` readonly class (name, phone, age, national_id, gender)
- [x] T005 [P] Create `app/DTOs/DoctorDTO.php` readonly class (id, name, specialty, fee_type, fee_value)
- [x] T006 [P] Update `resources/views/app.blade.php` to add `lang="ar" dir="rtl"` on `<html>` element
- [x] T007 [P] Add Arabic RTL color tokens to `resources/css/app.css` (primary, accent, danger, warning, success, border, surface, text scales from HTML prototype CSS variables)
- [x] T008 [P] Create shared Vue layout `resources/js/components/layout/AppLayout.vue` (sidebar + topbar shell, role-aware nav items)
- [x] T009 [P] Create `resources/js/components/layout/Sidebar.vue` (navigation items per HTML prototype: dashboard, booking, clinic, labs, surgery, lasik, laser, accounting, inventory, insurance, reports, admin — filtered by user permissions)
- [x] T010 [P] Create `resources/js/components/layout/Topbar.vue` (page title, search bar, notification bell, new booking button)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that ALL user stories depend on — migrations, RBAC seeding, shared Vue components, Inertia middleware.

**⚠️ CRITICAL**: No user story implementation can begin until this phase is complete.

- [x] T011 Update `app/Http/Middleware/HandleInertiaRequests.php` to share: `auth_user`, `permissions` (Spatie can()), `settings` (hospital name, etc.) in the `share()` method
- [x] T012 [P] Create migration `database/migrations/2026_04_14_200000_create_doctors_table.php`
- [x] T013 [P] Create migration `database/migrations/2026_04_14_200003_create_bookings_table.php`
- [x] T014 [P] Create migration `database/migrations/2026_04_14_200004_create_accounts_table.php`
- [x] T015 [P] Create migration `database/migrations/2026_04_14_200001_create_services_table.php`
- [x] T016 [P] Create migration `database/migrations/2026_04_14_200002_create_insurance_companies_table.php`
- [x] T017 [P] Create migration `database/migrations/2026_04_14_200005_create_settings_table.php`
- [x] T018 [P] Create migration `database/migrations/2026_04_14_200006_create_activity_logs_table.php`
- [ ] T019 Run `php artisan migrate` to apply all Phase 2 migrations  *(manual — run: `php artisan migrate`)*
- [x] T020 Create `database/seeders/RolesPermissionsSeeder.php` — seed 6 roles and all 34 permissions
- [x] T021 Create `database/seeders/AccountsSeeder.php` — seed 13 default GL accounts (1000–4000) per spec §5.9
- [x] T022 Create `database/seeders/SettingsSeeder.php` — seed hospital_name, hospital_specialty, hospital_address, mrn_format, low_stock_alerts_enabled
- [x] T023 Create `database/seeders/AdminUserSeeder.php` — seed default admin user, assign `admin` role via Spatie
- [ ] T024 Run `php artisan db:seed` to apply all seeders  *(manual — run: `php artisan db:seed`)*
- [x] T025 [P] Create shared Vue component `resources/js/components/shared/DataTable.vue` (RTL table with sortable headers, pagination, row hover, actions slot)
- [x] T026 [P] Create shared Vue component `resources/js/components/shared/Modal.vue` (overlay, header, body, footer slots, sizes: sm/md/lg/xl)
- [x] T027 [P] Create shared Vue component `resources/js/components/shared/StatCard.vue` (stat label, value, change indicator, color bar, 5 color variants)
- [x] T028 [P] Create shared Vue component `resources/js/components/shared/Badge.vue` (status badge with dot — 14 variants)
- [x] T029 [P] Create shared Vue component `resources/js/components/shared/SearchBar.vue` (search icon + input + clear button)
- [x] T030 [P] Create shared Vue component `resources/js/components/shared/DateFilter.vue` (from/to date range pickers with apply/clear buttons)
- [x] T031 [P] Create shared Vue component `resources/js/components/shared/ExportBar.vue` (Excel download + Print buttons)
- [x] T032 [P] Create shared Vue composable `resources/js/composables/useNotifications.ts`
- [x] T033 [P] Create shared Vue composable `resources/js/composables/useExport.ts`
- [x] T034 [P] Create shared Vue composable `resources/js/composables/usePrint.ts`

**Checkpoint**: All migrations applied, seeders run, shared components exist — user story work can begin.

---

## Phase 3: User Story 1 — Patient Registration & Booking (Priority: P1) 🎯 MVP

**Goal**: Receptionist registers a patient, books an appointment, manages queue status.
**Independent Test**: Login as `استقبال`, create new booking via "حجز جديد", confirm it appears with correct MRN and dept badge in table.

### Implementation — Booking Module

- [x] T035 [P] [US1] Create `Modules/Booking/Models/Booking.php`
- [x] T036 [P] [US1] Create `Modules/Doctor/Models/Doctor.php`
- [x] T037 [P] [US1] Create `Modules/Booking/DTOs/BookingData.php`
- [x] T038 [P] [US1] Create `Modules/Booking/DTOs/BookingFilterData.php`
- [x] T039 [P] [US1] Create `Modules/Booking/Repositories/Contracts/BookingRepositoryInterface.php`
- [x] T040 [P] [US1] Create `Modules/Booking/Repositories/BookingRepository.php`
- [x] T041 [P] [US1] Create `Modules/Booking/Services/MrnGeneratorService.php`
- [x] T042 [US1] Create `Modules/Booking/Services/BookingService.php`
- [x] T043 [US1] Create `Modules/Booking/Actions/CreateBookingAction.php`
- [x] T044 [US1] Create `Modules/Booking/Actions/UpdateBookingAction.php`
- [x] T045 [US1] Create `Modules/Booking/Actions/UpdateBookingStatusAction.php`
- [x] T046 [US1] Create `Modules/Booking/Actions/CancelBookingAction.php`
- [x] T047 [US1] Create `Modules/Booking/Http/Requests/StoreBookingRequest.php`
- [x] T048 [US1] Create `Modules/Booking/Http/Requests/UpdateBookingRequest.php`
- [x] T049 [US1] Create `Modules/Booking/Controllers/BookingController.php`
- [x] T050 [US1] Create `Modules/Booking/Controllers/BookingStatusController.php`
- [x] T051 [US1] Register repository binding in `Modules/Booking/Providers/BookingServiceProvider.php`
- [x] T052 [US1] Define routes in `Modules/Booking/Routes/web.php`
- [x] T053 [US1] Create Vue page `resources/js/pages/booking/Index.vue`
- [x] T054 [US1] Create Vue partial `resources/js/pages/booking/Partials/BookingForm.vue`
- [x] T055 [US1] Create Vue page `resources/js/pages/booking/Receipt.vue` (print-only, no layout)

**Checkpoint**: Booking CRUD fully functional. Receptionist can register patients, view queue, change statuses. MRN generates correctly.

---

## Phase 4: User Story 2 — Clinical Consultation & Medical File (Priority: P1)

**Goal**: Doctor views daily queue, records clinical findings, saves consultation, views patient history.
**Independent Test**: Login as `طبيب`, open a waiting booking in Clinic, fill the clinic sheet form, save — status changes to `مكتمل` and history shows the visit.

### Implementation — Clinic Module

- [x] T056 [P] [US2] Create `Modules/Clinic/Models/ClinicSheet.php`
- [x] T057 [P] [US2] Create `Modules/Clinic/DTOs/ClinicSheetData.php`
- [x] T058 [P] [US2] Create `Modules/Clinic/Repositories/Contracts/ClinicSheetRepositoryInterface.php`
- [x] T059 [P] [US2] Create `Modules/Clinic/Repositories/ClinicSheetRepository.php`
- [x] T060 [US2] Create `Modules/Clinic/Services/ClinicService.php`
- [x] T061 [US2] Create `Modules/Clinic/Actions/RecordClinicSheetAction.php`
- [x] T062 [US2] Create `Modules/Clinic/Actions/ReferPatientAction.php`
- [x] T063 [US2] Create `Modules/Clinic/Http/Requests/StoreClinicSheetRequest.php`
- [x] T064 [US2] Create `Modules/Clinic/Controllers/ClinicController.php`
- [x] T065 [US2] Register binding + define routes (`Modules/Clinic/Providers/ClinicServiceProvider.php` + `Modules/Clinic/Routes/web.php`)
- [x] T066 [US2] Create Vue page `resources/js/pages/clinic/Index.vue`
- [x] T067 [US2] Create Vue page `resources/js/pages/clinic/Patient.vue`

**Checkpoint**: Clinic queue operational. Doctors can complete consultations and view full patient history.

---

## Phase 5: User Story 3 — Surgery & Procedure Scheduling (Priority: P2)

**Goal**: Schedule surgeries/LASIK/Laser procedures, record supplies, track OR status.
**Independent Test**: Create a surgery booking, assign OR bed, record supplies used — doctor claim shows dr_share = paid - supply_total.

### Implementation — Surgery, Lasik, Laser Modules

- [ ] T068 [P] [US3] Create migration `database/migrations/xxxx_create_or_rooms_table.php` (int PK, name, status enum, total_beds)
- [ ] T069 [P] [US3] Create migration `database/migrations/xxxx_create_or_beds_table.php` (int PK, room_id FK, bed_number, status enum)
- [ ] T070 [P] [US3] Create migration `database/migrations/xxxx_create_surgeries_table.php` (all columns from data-model.md §Surgery including dept enum to distinguish Surgery/Lasik/Laser, supplies_used JSON, supply_total)
- [ ] T071 [P] [US3] Run `php artisan migrate` for or_rooms, or_beds, surgeries tables
- [ ] T072 [P] [US3] Create `Modules/Surgery/Models/Surgery.php` (HasUlids, fillable, casts for JSON + enums, relationships: booking(), surgeon(), orBed())
- [ ] T073 [P] [US3] Create `Modules/Surgery/Models/OrRoom.php` + `Modules/Surgery/Models/OrBed.php`
- [ ] T074 [P] [US3] Create `Modules/Surgery/DTOs/SurgeryData.php` + `Modules/Surgery/DTOs/SuppliesUsedData.php`
- [ ] T075 [P] [US3] Create `Modules/Surgery/Repositories/Contracts/SurgeryRepositoryInterface.php`
- [ ] T076 [P] [US3] Create `Modules/Surgery/Repositories/SurgeryRepository.php`
- [ ] T077 [US3] Create `Modules/Surgery/Services/SurgeryService.php` (schedule surgery, update status, compute supply_total from supplies_used JSON array, check OR bed availability)
- [ ] T078 [US3] Create `Modules/Surgery/Actions/ScheduleSurgeryAction.php` (validates OR bed available for time slot, calls SurgeryService, logs activity)
- [ ] T079 [US3] Create `Modules/Surgery/Actions/RecordSurgeryReportAction.php` (saves op_report, post_op_notes, complications, marks surgery completed)
- [ ] T080 [US3] Create `Modules/Surgery/Actions/RecordSuppliesUsedAction.php` (updates supplies_used JSON + supply_total on Surgery, deducts from inventory_items.quantity via InventoryService)
- [ ] T081 [US3] Create `Modules/Surgery/Http/Requests/StoreSurgeryRequest.php` + `RecordSuppliesRequest.php`
- [ ] T082 [US3] Create `Modules/Surgery/Controllers/SurgeryController.php` + `OrRoomController.php`
- [ ] T083 [US3] Register bindings + routes in `Modules/Surgery/Providers/SurgeryServiceProvider.php` and `Modules/Surgery/Routes/web.php` (GET /surgery, POST /surgery, PATCH /surgery/{id}/report, PATCH /surgery/{id}/supplies, GET /surgery/or-rooms)
- [ ] T084 [P] [US3] Create Lasik module mirror: `Modules/Lasik/` — same structure as Surgery, routes prefixed `/lasik`, dept=lasik filter
- [ ] T085 [P] [US3] Create Laser module mirror: `Modules/Laser/` — same structure, routes `/laser`, dept=laser, no OR bed (laser procedures use service-share fee formula, not supply deduction)
- [ ] T086 [US3] Create Vue page `resources/js/Pages/Surgery/Index.vue` (procedure list with status pipeline, OR room availability grid, schedule form in Modal, supplies recorder panel)
- [ ] T087 [P] [US3] Create Vue page `resources/js/Pages/Lasik/Index.vue` (same structure as Surgery, labeled for LASIK procedures)
- [ ] T088 [P] [US3] Create Vue page `resources/js/Pages/Laser/Index.vue` (same structure, no OR bed selector)

**Checkpoint**: All three procedure departments (Surgery, LASIK, Laser) can schedule and complete procedures. Supply deduction updates inventory.

---

## Phase 6: User Story 4 — Billing, Invoicing & Treasury (Priority: P2)

**Goal**: Accountant generates invoices, records payments, reconciles treasury.
**Independent Test**: Mark a completed booking as paid → treasury entry auto-created → treasury balance increases → journal entry auto-posted.

### Implementation — Accounting Module

- [ ] T089 [P] [US4] Create migration `database/migrations/xxxx_create_journal_entries_table.php` (ULID PK, date, description, debit_account_id FK, credit_account_id FK, amount, reference, source enum, booking_id FK nullable, created_by FK)
- [ ] T090 [P] [US4] Create migration `database/migrations/xxxx_create_treasury_entries_table.php` (ULID PK, type enum, description, amount, date, reference_no, beneficiary, account_id FK nullable, source enum, booking_id FK nullable, created_by FK)
- [ ] T091 [US4] Run `php artisan migrate` for journal_entries, treasury_entries
- [ ] T092 [P] [US4] Create `Modules/Accounting/Models/Account.php` (HasUlids, fillable, parent() and children() self-relationships)
- [ ] T093 [P] [US4] Create `Modules/Accounting/Models/JournalEntry.php` (HasUlids, debitAccount(), creditAccount(), booking() relationships)
- [ ] T094 [P] [US4] Create `Modules/Accounting/Models/TreasuryEntry.php` (HasUlids, account(), booking() relationships)
- [ ] T095 [P] [US4] Create `Modules/Accounting/Repositories/Contracts/AccountRepositoryInterface.php` + `JournalRepositoryInterface.php` + `TreasuryRepositoryInterface.php`
- [ ] T096 [P] [US4] Create `Modules/Accounting/Repositories/AccountRepository.php` + `JournalRepository.php` + `TreasuryRepository.php`
- [ ] T097 [US4] Create `Modules/Accounting/Services/AccountingService.php` (getBalance(accountId, from, to), getTrialBalanceData, post journal entry with debit≠credit guard)
- [ ] T098 [US4] Create `Modules/Accounting/Services/TrialBalanceService.php` (computes debit/credit totals per account for date range, asserts totals balance)
- [ ] T099 [US4] Create `Modules/Accounting/Services/IncomeStatementService.php` (groups revenues and expenses, computes net income for period)
- [ ] T100 [US4] Create `Modules/Accounting/Actions/PostJournalEntryAction.php` (validates debit≠credit, calls JournalRepository, updates running account balances)
- [ ] T101 [US4] Create `Modules/Accounting/Actions/RecordTreasuryEntryAction.php` (calls TreasuryRepository, optionally links to booking)
- [ ] T102 [US4] Create `Modules/Accounting/Actions/AutoPostBookingPaymentAction.php` (triggered when booking pay_status → paid: creates treasury_entry + 2 journal_entries per spec §auto-posting rules)
- [ ] T103 [US4] Hook `AutoPostBookingPaymentAction` into `UpdateBookingAction` via Booking model event or explicit call in BookingService when pay_status changes to `paid`
- [ ] T104 [US4] Create `Modules/Accounting/Http/Requests/StoreJournalEntryRequest.php` (includes `different:debit_account_id` rule on credit_account_id)
- [ ] T105 [US4] Create `Modules/Accounting/Http/Requests/StoreTreasuryEntryRequest.php`
- [ ] T106 [US4] Create `Modules/Accounting/Controllers/TreasuryController.php` (index with daily summary, store)
- [ ] T107 [US4] Create `Modules/Accounting/Controllers/JournalController.php` (index with all accounts in props, store)
- [ ] T108 [US4] Create `Modules/Accounting/Controllers/ChartOfAccountsController.php` (index as tree, store, update)
- [ ] T109 [US4] Create `Modules/Accounting/Controllers/TrialBalanceController.php` (index with date range filter, returns balanced data)
- [ ] T110 [US4] Create `Modules/Accounting/Controllers/IncomeStatementController.php` (index with date range)
- [ ] T111 [US4] Create `Modules/Accounting/Controllers/AccountStatementController.php` (index — consolidated statement per account)
- [ ] T112 [US4] Register all bindings + routes in `Modules/Accounting/Providers/AccountingServiceProvider.php` and `Modules/Accounting/Routes/web.php`
- [ ] T113 [US4] Create Vue page `resources/js/Pages/Accounting/Treasury.vue` (daily in/out entries, running balance, summary cards, add entry modal)
- [ ] T114 [US4] Create Vue page `resources/js/Pages/Accounting/Journal.vue` (journal entry list, add entry form with account selectors)
- [ ] T115 [US4] Create Vue page `resources/js/Pages/Accounting/ChartOfAccounts.vue` (tree view grouped by group type, add/edit account modal)
- [ ] T116 [US4] Create Vue page `resources/js/Pages/Accounting/TrialBalance.vue` (two-column debit/credit table, grand totals row, date range filter, export bar)
- [ ] T117 [US4] Create Vue page `resources/js/Pages/Accounting/IncomeStatement.vue` (revenues section, expenses section, net income row — from HTML prototype `.is-section` styles)
- [ ] T118 [US4] Create Vue page `resources/js/Pages/Accounting/AccountStatement.vue` (account selector, chronological transaction list with running balance)

**Checkpoint**: Full double-entry accounting functional. Payments auto-post journal entries. Trial balance is always balanced.

---

## Phase 7: User Story 5 — Inventory, Suppliers & Purchases (Priority: P3)

**Goal**: Store keeper manages stock, creates purchase invoices, issues dispensing permits, sees low-stock alerts.
**Independent Test**: Add item with min_quantity=5, qty=3 → notification bell shows alert count → issue dispensing permit for 3 units → stock reaches 0.

### Implementation — Inventory Module

- [ ] T119 [P] [US5] Create migration `database/migrations/xxxx_create_suppliers_table.php`
- [ ] T120 [P] [US5] Create migration `database/migrations/xxxx_create_inventory_items_table.php` (all columns from data-model.md including min_quantity, unit_cost, sell_price, expiry_date, supplier_id FK)
- [ ] T121 [P] [US5] Create migration `database/migrations/xxxx_create_purchase_invoices_table.php` + `xxxx_create_purchase_invoice_items_table.php`
- [ ] T122 [P] [US5] Create migration `database/migrations/xxxx_create_stock_permits_table.php` + `xxxx_create_stock_permit_items_table.php`
- [ ] T123 [US5] Run `php artisan migrate` for inventory tables
- [ ] T124 [P] [US5] Create `Modules/Inventory/Models/InventoryItem.php` (HasUlids, fillable, isLowStock() accessor: quantity <= min_quantity, supplier() relationship)
- [ ] T125 [P] [US5] Create `Modules/Inventory/Models/Supplier.php` + `PurchaseInvoice.php` + `PurchaseInvoiceItem.php` + `StockPermit.php` + `StockPermitItem.php`
- [ ] T126 [P] [US5] Create `Modules/Inventory/Models/Service.php` (the services table model — placed in Inventory module as per plan)
- [ ] T127 [P] [US5] Create `Modules/Inventory/Repositories/Contracts/InventoryRepositoryInterface.php` + `SupplierRepositoryInterface.php`
- [ ] T128 [P] [US5] Create `Modules/Inventory/Repositories/InventoryRepository.php` + `SupplierRepository.php`
- [ ] T129 [US5] Create `Modules/Inventory/Services/InventoryService.php` (adjustQuantity() — adds or deducts stock qty with zero-floor guard, getLowStockItems())
- [ ] T130 [US5] Create `Modules/Inventory/Services/StockAlertService.php` (getLowStockCount() — used by HandleInertiaRequests to populate notification badge)
- [ ] T131 [US5] Create `Modules/Inventory/Services/PurchaseInvoiceService.php` (creates invoice + line items, calls InventoryService::adjustQuantity for each item, posts accounting entry)
- [ ] T132 [US5] Create `Modules/Inventory/Actions/ReceivePurchaseInvoiceAction.php`
- [ ] T133 [US5] Create `Modules/Inventory/Actions/IssueStockPermitAction.php` (type=out: validates sufficient qty, calls InventoryService::adjustQuantity, logs activity)
- [ ] T134 [US5] Create `Modules/Inventory/Actions/AddStockPermitAction.php` (type=in: calls InventoryService::adjustQuantity with positive delta)
- [ ] T135 [US5] Create `Modules/Inventory/Actions/StockTakeAdjustmentAction.php` (computes variance between physical count and system qty, posts adjustment)
- [ ] T136 [US5] Create all Inventory HTTP Requests in `Modules/Inventory/Http/Requests/`
- [ ] T137 [US5] Create `Modules/Inventory/Controllers/InventoryController.php` + `SupplierController.php` + `PurchaseInvoiceController.php` + `StockPermitController.php` + `StockTakeController.php` + `PurchaseReturnController.php`
- [ ] T138 [US5] Create `Modules/Inventory/Controllers/ServiceController.php` (CRUD for services table + Excel import via maatwebsite/laravel-excel `ServicesImport` class)
- [ ] T139 [US5] Create `Modules/Inventory/Imports/ServicesImport.php` (implements `ToModel`, `WithHeadings`, `WithUpserts` — adds new, updates existing by name match)
- [ ] T140 [US5] Create `Modules/Inventory/Imports/InventoryImport.php` (same pattern for inventory items)
- [ ] T141 [US5] Register all bindings + routes in `Modules/Inventory/Providers/InventoryServiceProvider.php` and `Modules/Inventory/Routes/web.php`
- [ ] T142 [US5] Update `app/Http/Middleware/HandleInertiaRequests.php` to include `low_stock_count` from `StockAlertService::getLowStockCount()` in shared Inertia data
- [ ] T143 [US5] Create Vue page `resources/js/Pages/Inventory/Index.vue` (item list with low-stock row highlighting, add item modal, Excel import button)
- [ ] T144 [P] [US5] Create Vue page `resources/js/Pages/Inventory/Suppliers.vue`
- [ ] T145 [P] [US5] Create Vue page `resources/js/Pages/Inventory/PurchaseInvoices.vue` (invoice list, create invoice with line items)
- [ ] T146 [P] [US5] Create Vue page `resources/js/Pages/Inventory/StockPermit.vue` (dispensing/addition permit form with item multi-select + qty)
- [ ] T147 [P] [US5] Create Vue page `resources/js/Pages/Inventory/Services.vue` (services CRUD table with Excel import)

**Checkpoint**: Full inventory management. Low-stock alerts appear in notification bell. Purchases update stock.

---

## Phase 8: User Story 6 — Financial Accounting & Reporting (Priority: P3)

**Goal**: Accountant views trial balance, income statement, and P&L for selected periods.
**Independent Test**: Post manual journal entries, open Trial Balance → debit total equals credit total → Income Statement shows correct net income.

**Note**: Accounting module was created in Phase 6 (US4). This phase adds the remaining financial report controllers and pages.

- [ ] T148 [US6] Create `Modules/Accounting/Controllers/LedgerController.php` (دفتر الأستاذ — account ledger with all transactions for selected account + running balance)
- [ ] T149 [US6] Add route GET /ledger to `Modules/Accounting/Routes/web.php`
- [ ] T150 [US6] Create Vue page `resources/js/Pages/Accounting/Ledger.vue` (account selector dropdown, transaction table, running balance column, date filter)

**Checkpoint**: All financial accounting screens functional — Chart of Accounts, Journal, Trial Balance, Income Statement, Account Statement, Ledger.

---

## Phase 9: User Story 7 — Doctor Management, Shifts & Entitlements (Priority: P3)

**Goal**: Admin manages doctors, tracks shifts, calculates entitlements, records payouts.
**Independent Test**: View doctor claims for a date range → all 5 fee formula types produce correct dr_share and center_share values.

### Implementation — Doctor Module

- [ ] T151 [P] [US7] Create migration `database/migrations/xxxx_create_doctor_shifts_table.php` + `xxxx_create_doctor_payments_table.php`
- [ ] T152 [US7] Run `php artisan migrate` for doctor tables
- [ ] T153 [P] [US7] Create `Modules/Doctor/Models/DoctorShift.php` + `Modules/Doctor/Models/DoctorPayment.php`
- [ ] T154 [P] [US7] Create `Modules/Doctor/Repositories/Contracts/DoctorRepositoryInterface.php`
- [ ] T155 [P] [US7] Create `Modules/Doctor/Repositories/DoctorRepository.php`
- [ ] T156 [US7] Create `Modules/Doctor/Services/DoctorService.php` (CRUD, getActiveForDept, linkUserAccount)
- [ ] T157 [US7] Create `Modules/Doctor/Services/DoctorClaimsService.php` — implement all 5 fee calculation strategies:
  - `ClinicFeeStrategy`: dr_share = paid - service.center_share
  - `LabsFeeStrategy`: same as clinic
  - `LaserFeeStrategy`: same as clinic (service-defined share)
  - `SurgeryFeeStrategy`: dr_share = paid - surgery.supply_total
  - `LasikFeeStrategy`: same as surgery
  - `InsuranceSurgeryStrategy`: dr_share=fixed amount; center=total-supplies-dr_share
  - Dispatch to correct strategy based on booking.dept + booking.pay_method + doctor.fee_type
- [ ] T158 [US7] Create `Modules/Doctor/Actions/CreateDoctorAction.php` + `RecordDoctorPaymentAction.php` + `OpenDoctorShiftAction.php`
- [ ] T159 [US7] Create all Doctor HTTP Requests in `Modules/Doctor/Http/Requests/`
- [ ] T160 [US7] Create `Modules/Doctor/Controllers/DoctorController.php` + `DoctorClaimsController.php` + `DoctorPaymentController.php` + `DoctorShiftController.php`
- [ ] T161 [US7] Create `Modules/Doctor/Controllers/ShiftHandoverController.php` (records shift handover summary, changes shift status to handed_over)
- [ ] T162 [US7] Register all bindings + routes in `Modules/Doctor/Providers/DoctorServiceProvider.php` and `Modules/Doctor/Routes/web.php`
- [ ] T163 [US7] Create Vue page `resources/js/Pages/Doctor/Index.vue` (doctor list with fee type badges, add/edit modal)
- [ ] T164 [US7] Create Vue page `resources/js/Pages/Doctor/Claims.vue` (date range filter, per-doctor accordion with case breakdown table + totals, export bar — from HTML prototype `.dc-section .dc-doctor-hd .dc-table` styles)
- [ ] T165 [US7] Create Vue page `resources/js/Pages/Doctor/Payments.vue` (payment history per doctor, record payment modal)
- [ ] T166 [US7] Create Vue page `resources/js/Pages/Doctor/Shifts.vue` (shift schedule per doctor, open/close shift, handover button)

**Checkpoint**: Doctor entitlements calculate correctly for all 5 formula types. Shift handover recorded. Payouts tracked.

---

## Phase 10: User Story 8 — User Management, Roles & Permissions (Priority: P4)

**Goal**: Admin creates users, assigns roles, verifies access control enforcement.
**Independent Test**: Create user with `reception` role → login → can access /booking → cannot access /admin/system-log → 403 returned.

### Implementation — Admin Module

- [ ] T167 [P] [US8] Create `Modules/Admin/Models/Setting.php` (key-value store, group scopes)
- [ ] T168 [P] [US8] Create `Modules/Admin/Models/ActivityLog.php` (no updated_at, immutable)
- [ ] T169 [P] [US8] Create `Modules/Admin/Repositories/Contracts/UserRepositoryInterface.php`
- [ ] T170 [P] [US8] Create `Modules/Admin/Repositories/UserRepository.php`
- [ ] T171 [US8] Create `Modules/Admin/Services/UserManagementService.php` (createUser, assignRole, listUsers with roles, deactivateUser)
- [ ] T172 [US8] Create `Modules/Admin/Services/ActivityLogService.php` (log() method: inserts activity_log row, captures old/new values, IP address — called by all Actions)
- [ ] T173 [US8] Create `Modules/Admin/Actions/CreateUserAction.php` + `AssignRoleAction.php`
- [ ] T174 [US8] Create `Modules/Admin/Http/Requests/StoreUserRequest.php` + `UpdateSettingsRequest.php`
- [ ] T175 [US8] Create `Modules/Admin/Controllers/UserController.php` (index, store, update, destroy — manage user accounts + role assignment)
- [ ] T176 [US8] Create `Modules/Admin/Controllers/RoleController.php` (index — display roles and their permissions in grid, update permissions per role)
- [ ] T177 [US8] Create `Modules/Admin/Controllers/SettingsController.php` (index shows all setting groups, update saves key-value pairs)
- [ ] T178 [US8] Create `Modules/Admin/Controllers/SystemLogController.php` (index with user/module/action filters + date range)
- [ ] T179 [US8] Create `Modules/Admin/Controllers/ArchiveController.php` (list archived bookings and records)
- [ ] T180 [US8] Register all bindings + routes in `Modules/Admin/Providers/AdminServiceProvider.php` and `Modules/Admin/Routes/web.php` (all routes guarded by `users.manage` or `settings.manage`)
- [ ] T181 [US8] Create Vue page `resources/js/Pages/Admin/Users.vue` (user cards with role pills from HTML prototype `.user-card .role-pill`, add user modal with role selector)
- [ ] T182 [US8] Create Vue page `resources/js/Pages/Admin/Roles.vue` (role cards with permission checkbox grid from HTML prototype `.role-card .perm-grid`)
- [ ] T183 [US8] Create Vue page `resources/js/Pages/Admin/Settings.vue` (grouped settings sections from HTML prototype `.settings-section .settings-title`)
- [ ] T184 [US8] Create Vue page `resources/js/Pages/Admin/SystemLog.vue` (activity log table with filters)
- [ ] T185 [US8] Create Vue page `resources/js/Pages/Admin/Archive.vue`

**Checkpoint**: RBAC fully enforced. All 6 roles have correct access. System log records all mutations.

---

## Phase 11: User Story 9 — Reports & Data Export (Priority: P4)

**Goal**: Management views all 10+ reports with date filters, exports to Excel, prints.
**Independent Test**: Open Department Revenue Report, filter by date range, verify totals match, click Export → valid .xlsx downloads.

### Implementation — Reporting Module + Insurance Module

- [ ] T186 [P] [US9] Create migration `database/migrations/xxxx_create_price_lists_table.php` + `xxxx_create_price_list_items_table.php`
- [ ] T187 [US9] Run `php artisan migrate` for price lists
- [ ] T188 [P] [US9] Create `Modules/Insurance/Models/InsuranceCompany.php` + `Modules/Insurance/Models/PriceList.php` + `Modules/Insurance/Models/PriceListItem.php`
- [ ] T189 [P] [US9] Create `Modules/Insurance/Repositories/Contracts/InsuranceRepositoryInterface.php`
- [ ] T190 [P] [US9] Create `Modules/Insurance/Repositories/InsuranceRepository.php`
- [ ] T191 [US9] Create `Modules/Insurance/Services/InsuranceService.php` (CRUD for companies + price lists, calculateInsuranceCoverage(serviceId, companyId))
- [ ] T192 [US9] Create `Modules/Insurance/Actions/CreateInsuranceCompanyAction.php` + `ManagePriceListAction.php`
- [ ] T193 [US9] Create `Modules/Insurance/Controllers/InsuranceCompanyController.php` + `PriceListController.php`
- [ ] T194 [US9] Register all bindings + routes in `Modules/Insurance/Providers/InsuranceServiceProvider.php` and `Modules/Insurance/Routes/web.php`
- [ ] T195 [US9] Create Vue page `resources/js/Pages/Insurance/Companies.vue` (company list with CRUD modal, coverage % badge)
- [ ] T196 [US9] Create Vue page `resources/js/Pages/Insurance/PriceLists.vue` (price list with per-service override table, printable price sheet)
- [ ] T197 [US9] Create `Modules/Reporting/Services/ReportingService.php` (all 10 report data queries: dept revenue, cases, doctor claims, doctor payments, insurance claims, inventory movement, purchase prices, profit/loss, expense analysis, system log)
- [ ] T198 [US9] Create `Modules/Reporting/Services/ExcelExportService.php` (dispatch to correct `maatwebsite/laravel-excel` Export class based on report type)
- [ ] T199 [P] [US9] Create Excel Export classes in `Modules/Reporting/Exports/` (one per report type: DeptRevenueExport, CasesExport, DoctorClaimsExport, DoctorPaymentsExport, InsuranceClaimsExport, InventoryMovementExport, ProfitLossExport — each implements `FromQuery + WithHeadings`)
- [ ] T200 [US9] Create `Modules/Reporting/Controllers/DashboardController.php` (stats, recent bookings, dept revenue today, low stock count — from contracts/reporting.md)
- [ ] T201 [US9] Create `Modules/Reporting/Controllers/DeptRevenueReportController.php`
- [ ] T202 [US9] Create `Modules/Reporting/Controllers/CasesReportController.php`
- [ ] T203 [US9] Create `Modules/Reporting/Controllers/DoctorClaimsReportController.php` (delegates to DoctorClaimsService for fee calculations)
- [ ] T204 [US9] Create `Modules/Reporting/Controllers/DoctorPaymentsReportController.php`
- [ ] T205 [US9] Create `Modules/Reporting/Controllers/InsuranceReportController.php`
- [ ] T206 [US9] Create `Modules/Reporting/Controllers/InventoryMovementController.php`
- [ ] T207 [US9] Create `Modules/Reporting/Controllers/PurchasePriceReportController.php`
- [ ] T208 [US9] Create `Modules/Reporting/Controllers/ProfitLossController.php`
- [ ] T209 [US9] Create `Modules/Reporting/Controllers/ExpenseAnalysisController.php`
- [ ] T210 [US9] Register all routes in `Modules/Reporting/Routes/web.php` (GET /dashboard, GET /reports/{type}, GET /reports/{type}/export — each guarded by appropriate permission)
- [ ] T211 [US9] Create Vue page `resources/js/Pages/Dashboard/Index.vue` (stat cards row, dept revenue today bar, recent bookings table, notifications — from HTML prototype `#pg-dashboard`)
- [ ] T212 [US9] Create Vue page `resources/js/Pages/Reporting/Index.vue` (report card grid from HTML prototype `.report-card` style — 10 clickable cards)
- [ ] T213 [US9] Create Vue page `resources/js/Pages/Reporting/DeptRevenue.vue` (date filter, per-dept per-doctor revenue table, totals, ExportBar)
- [ ] T214 [US9] Create Vue page `resources/js/Pages/Reporting/CasesReport.vue`
- [ ] T215 [US9] Create Vue page `resources/js/Pages/Reporting/DoctorClaims.vue` (same layout as Doctor/Claims.vue — doctor entitlement breakdown, ExportBar)
- [ ] T216 [US9] Create Vue page `resources/js/Pages/Reporting/DoctorPayments.vue`
- [ ] T217 [US9] Create Vue page `resources/js/Pages/Reporting/InsuranceClaims.vue`
- [ ] T218 [US9] Create Vue page `resources/js/Pages/Reporting/InventoryMovement.vue`
- [ ] T219 [US9] Create Vue page `resources/js/Pages/Reporting/ProfitLoss.vue`
- [ ] T220 [US9] Create Vue page `resources/js/Pages/Reporting/ExpenseAnalysis.vue`

**Checkpoint**: All reports load with correct data. Excel export works for all 10 report types. Insurance module fully functional.

---

## Phase 12: Polish & Cross-Cutting Concerns

**Purpose**: Auth login page, Labs module, sales invoice, purchase returns, stocktake, final integration.

- [ ] T221 [P] Create Vue page `resources/js/Pages/Auth/Login.vue` (Arabic login form matching HTML prototype login-screen: hospital logo, eye SVG, username/password fields — role selector removed; role is server-assigned)
- [ ] T222 [P] Implement Labs module `Modules/Labs/` (migration for diagnostic_results, Model, Repository interface, Repository, Service, Action, Controller, Routes, Vue page `resources/js/Pages/Labs/Index.vue`)
- [ ] T223 [P] Create `Modules/Accounting/Controllers/SalesInvoiceController.php` (فاتورة البيع — generates invoice from booking, applies insurance coverage, triggers AutoPostBookingPaymentAction) + Vue page `resources/js/Pages/Accounting/SalesInvoice.vue`
- [ ] T224 [P] Create `Modules/Inventory/Controllers/PurchaseReturnController.php` (مردودات المشتريات — returns items to supplier, adjusts inventory quantity upward, posts accounting reversal) + Vue page `resources/js/Pages/Inventory/PurchaseReturns.vue`
- [ ] T225 [P] Create `Modules/Inventory/Controllers/StockTakeController.php` (تسوية الجرد — physical count entry, variance calculation, adjustment posting) + Vue page `resources/js/Pages/Inventory/StockTake.vue`
- [ ] T226 Create `resources/js/Pages/Booking/PatientFile.vue` (ملف المريض الطبي — cross-module patient history: all bookings, clinic sheets, diagnostics, surgeries displayed chronologically by file_no)
- [ ] T227 Create `resources/js/Pages/Doctor/ShiftHandover.vue` (تسليم الورديات — shift summary: patient count, revenue, pending cases, hand-over signature/notes)
- [ ] T228 Create `resources/js/Pages/Accounting/DailyJournal.vue` (قيود اليومية — alias to Journal.vue with date defaulted to today; ensure this route exists at GET /daily-journal)
- [ ] T229 [P] Add `@media print` CSS rules to Tailwind output or in `resources/css/app.css` (hide sidebar, topbar, action buttons; show full report content; format for A4 RTL paper)
- [ ] T230 Run `composer test` (pint + phpunit) and fix any failures
- [ ] T231 Run `npm run types:check` and `npm run lint:check` and fix any TypeScript / ESLint errors
- [ ] T232 Perform golden-path validation per `specs/001-eye-hospital-hms/quickstart.md` checklist (all 6 flows manually verified)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 completion — **BLOCKS ALL user stories**
- **Phase 3 (US1 Booking)**: Depends on Phase 2 — first MVP
- **Phase 4 (US2 Clinic)**: Depends on Phase 3 (needs bookings to exist)
- **Phase 5 (US3 Surgery)**: Depends on Phase 3 (needs bookings) + Phase 5 uses Inventory (coordinate T080 with Phase 7)
- **Phase 6 (US4 Accounting)**: Depends on Phase 3 (auto-posts from bookings)
- **Phase 7 (US5 Inventory)**: Depends on Phase 2 only — can run parallel with Phase 4/5/6
- **Phase 8 (US6 Accounting reports)**: Depends on Phase 6
- **Phase 9 (US7 Doctor)**: Depends on Phase 3 (uses bookings for claims calculation)
- **Phase 10 (US8 Admin)**: Depends on Phase 2 (Spatie already seeded); most tasks are independent
- **Phase 11 (US9 Reporting)**: Depends on all prior phases (aggregates all data)
- **Phase 12 (Polish)**: Depends on all phases

### User Story Dependencies

- **US1 (P1) Booking**: No story dependency — can start after Phase 2 ✅ MVP
- **US2 (P1) Clinic**: Depends on US1 (clinic sheet linked to booking)
- **US3 (P2) Surgery**: Depends on US1 (surgery linked to booking)
- **US4 (P2) Accounting**: Depends on US1 (auto-post on booking payment)
- **US5 (P3) Inventory**: Depends on US1 only (supplies deducted from surgery booking)
- **US6 (P3) Financial Reports**: Depends on US4 (accounting data)
- **US7 (P3) Doctors**: Depends on US1 (claims from bookings)
- **US8 (P4) Admin**: Independent (can run after Phase 2)
- **US9 (P4) Reports**: Depends on all stories (aggregates everything)

### Parallel Opportunities Within Stories

```bash
# Phase 2: All migrations can be created in parallel (T012–T018)
# Phase 2: All shared Vue components can be built in parallel (T025–T034)

# Phase 3 (US1): Models + DTOs + Repository contracts can be created in parallel
Task: T035 Create Booking Model
Task: T036 Create Doctor Model
Task: T037 Create BookingData DTO
Task: T038 Create BookingFilterData DTO
Task: T039 Create BookingRepositoryInterface

# Phase 5 (US3): Lasik and Laser modules can be created in parallel after Surgery
Task: T084 Create Lasik module (parallel with T085)
Task: T085 Create Laser module

# Phase 7 (US5): Multiple inventory models are independent
Task: T124 InventoryItem model
Task: T125 Supplier + PurchaseInvoice + StockPermit models (all parallel)
```

---

## Implementation Strategy

### MVP First (User Stories 1–2 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: US1 Booking → **Validate: Receptionist books patients**
4. Complete Phase 4: US2 Clinic → **Validate: Doctor completes consultations**
5. **STOP and VALIDATE**: 2 core clinical workflows operational

### Incremental Delivery

1. Setup + Foundation → Shared infrastructure ready
2. US1 Booking → Reception operational (MVP)
3. US2 Clinic → Clinical flow complete
4. US3 Surgery + US4 Accounting (parallel) → Procedures + billing live
5. US5 Inventory (parallel with above) → Stock management
6. US6 + US7 (parallel) → Financial reports + Doctor entitlements
7. US8 Admin → User management locked down
8. US9 Reporting → Full management visibility
9. Phase 12 Polish → Production-ready

### Parallel Team Strategy

With 3 developers after Phase 2:
- **Dev A**: US1 Booking + US2 Clinic + US3 Surgery
- **Dev B**: US4 Accounting + US6 Financial Reports
- **Dev C**: US5 Inventory + US7 Doctor + US8 Admin
- All converge on US9 Reporting + Phase 12 Polish

---

## Notes

- **[P]** = different files, no dependency on other in-progress tasks in same phase
- **[US#]** label maps every task to its user story for traceability
- Exact file paths required — tasks are immediately executable by an LLM without additional context
- `composer test` MUST pass before any story is marked complete
- Booking module is the keystone: all other modules depend on `bookings` table
- `DoctorClaimsService` (T157) is the highest-complexity implementation — allocate extra time
- `AutoPostBookingPaymentAction` (T102–T103) must be carefully tested to avoid duplicate accounting entries
