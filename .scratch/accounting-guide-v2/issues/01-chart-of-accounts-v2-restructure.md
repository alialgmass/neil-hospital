# 01: Chart of accounts v2.0 restructure + renumber migration

**What to build:** The chart of accounts matches الدليل المحاسبى v2.0. `AccountCode` (the single source of truth) carries every code the engine will post to. Liabilities are renumbered to the guide's scheme and all existing journal history still foots afterward with no amount changed. All new accounts are seeded — masters non-postable, sub-account ranges present, plus the new asset/revenue/expense/contra accounts. `AccountsSeeder`, the `database/seeders/Historical/*` seeders, and `docs/accounts-map.md` reflect the v2.0 chart.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `AccountCode` enum extended with: 1011 (development fund), 1080 (prepaid withholding tax), 1090–1096 (per-center cash tracking), 1120/1121, 1130/1131, 1140/1141, 1150/1151, 1160/1161 (gross-asset + contra-accumulated-depreciation pairs), 2041 (social insurance payable), 2060 (accrued expenses), 2070 (income-tax payable), 3030, 3040, 4060 (pentacam revenue), 4110–4150 (insurance revenue by department), 4910/4920 (contra-revenue), 5115 (contra-expense — supply cost recovered from doctor), 5211 (hospital social-insurance share), 5261–5265 (depreciation by asset class)
- [ ] Per-doctor (2201–2235), per-supplier (2301–2324), per-employee (2401–2416) sub-account ranges seeded under non-postable masters (2010 doctors, 2020 suppliers, 2030 net-salary master); first-cohort codes from the guide seeded
- [ ] Liabilities renumbered to the guide: 2030 → net-salary payable master, 2040 retired, 2041 → social insurance, 2050 → patient advances, 2060 → accrued expenses, 2070 → income-tax payable; old 2030 (VAT) moved to a non-colliding code or retired (VAT automation stays out of scope)
- [ ] Reversible migration remaps `accounts.code` and any `services.revenue_account_id` overrides; `journal_entries` FKs are by id so no economic change; a test proves total debits still equal total credits and every account balance is unchanged after migration
- [ ] `4230` (`DOCTOR_SUPPLY_COST_RECOVERY`) and the `4070` override case reworked per the 5115 decision; documented
- [ ] `AccountCode::nonPostableCodes()` extended with every new master (1030, 2010, 2020, net-salary master, 1090 group, 4100, …)
- [ ] Every `AccountCode` case resolves via `AccountResolver` (extend `AccountResolverTest`)
- [ ] `JournalService::record()` rejects a direct post to every new master account (test)
- [ ] `AccountsSeeder` and `database/seeders/Historical/*` updated so a fresh rebuild reproduces the guide's balances
- [ ] `docs/accounts-map.md` and `specs/001-eye-hospital-hms/contracts/accounting.md` regenerated for the v2.0 chart
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
