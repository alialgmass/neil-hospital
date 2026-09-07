# 02: Cost-center table + cost_center_id FK + auto-tag by department

**What to build:** Cost centers become real data, not code constants. A `cost_centers` table holds the seven canonical centers plus CC-ADMIN. `journal_entries` carries a `cost_center_id` foreign key and existing string `cost_center` values are migrated onto it. Every AutoPost/Process action resolves the center from the originating booking's department through one shared helper that never throws on an unmatched department (the current `AutoPostBookingPaymentAction::costCenter()` `\UnhandledMatchError` on Pentacam bookings is fixed).

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] `cost_centers` table (code, label, is_active) seeded with CC-CLINIC, CC-LAB, CC-PENTA, CC-LASER, CC-LASIK, CC-SURG, CC-INS, CC-ADMIN
- [ ] `CostCenter` enum reconciled to the guide codes (`CC-PENTA`, not `CC-PENTACAM`); enum stays the typed accessor, kept in sync with the table
- [ ] `journal_entries.cost_center_id` FK added; reversible migration backfills it from the existing string `cost_center` column
- [ ] Reporting services (`LedgerService`, `IncomeStatementService`, journal filters, `CostCenterController`) read the FK
- [ ] Single shared `CostCenter::forDepartment(Department): self` with a safe default arm; every `Department` case including `Pentacam` maps to a center
- [ ] Every AutoPost/Process action tags entries via that helper from the booking's department
- [ ] Test: a booking payment in every department (especially Pentacam) tags the correct center and does not throw
- [ ] Test: `CostCenterController` / cost-center report groups by the FK and returns the seeded centers
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
