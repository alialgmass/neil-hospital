# 01: Chart of accounts v2.0 restructure + renumber migration

**What to build:** The chart of accounts matches الدليل المحاسبى v2.0. `AccountCode` (the single source of truth) carries every code the engine will post to. Liabilities are renumbered to the guide's scheme and all existing journal history still foots afterward with no amount changed. All new accounts are seeded — masters non-postable, sub-account ranges present, plus the new asset/revenue/expense/contra accounts. `AccountsSeeder`, the `database/seeders/Historical/*` seeders, and `docs/accounts-map.md` reflect the v2.0 chart.

**Blocked by:** None (can start immediately)

**Status:** DONE (commit on `feat/accounting-guide-v2`)

**Implementation note:** the additive renumber + inventory master/leaf split + all new
v2.0 accounts landed. Making `2010/2020/2030` non-postable and seeding per-doctor /
per-supplier / per-employee (2201–2235 / 2301–2324 / 2401–2416) and per-payer
(1031–1047) sub-accounts is **deferred** into tickets 05/04, 10, and 06 respectively —
`AccountResolver::mustBePostableAndActive()` rejects any account with children, so a
master can only be flipped in the same slice that teaches its posting actions to
resolve the entity-specific leaf. Tests: `tests/Feature/Accounting/ChartOfAccountsV2Test.php`.

- [ ] `AccountCode` enum extended with: 1011 (development fund), 1080 (prepaid withholding tax), 1090–1096 (per-center cash tracking), 1120/1121, 1130/1131, 1140/1141, 1150/1151, 1160/1161 (gross-asset + contra-accumulated-depreciation pairs), 2041 (social insurance payable), 2060 (accrued expenses), 2070 (income-tax payable), 3030, 3040, 4060 (pentacam revenue), 4110–4150 (insurance revenue by department), 4910/4920 (contra-revenue), 5115 (contra-expense — supply cost recovered from doctor), 5211 (hospital social-insurance share), 5261–5265 (depreciation by asset class)
- [ ] Per-doctor (2201–2235), per-supplier (2301–2324), per-employee (2401–2416) sub-account ranges seeded under non-postable masters (2010 doctors, 2020 suppliers, 2030 net-salary master); first-cohort codes from the guide seeded
- [ ] Liabilities renumbered to the guide: 2030 → net-salary payable master, 2040 retired, 2041 → social insurance, 2050 → patient advances, 2060 → accrued expenses, 2070 → income-tax payable; old 2030 (VAT) moved to a non-colliding code or retired (VAT automation stays out of scope)
- [ ] Reversible migration remaps `accounts.code` and any `services.revenue_account_id` overrides; `journal_entries` FKs are by id so no economic change; a test proves total debits still equal total credits and every account balance is unchanged after migration
- [ ] `4230` (`DOCTOR_SUPPLY_COST_RECOVERY`) and the `4070` override case reworked per the 5115 decision; documented
- [x] `AccountCode::nonPostableCodes()` extended with the new *pure* masters (1050 inventory group, 4900 contra-revenue group, 5260 depreciation group). **Deferred:** 1030 / 2010 / 2020 / 2030 stay postable until tickets 06 / 05 / 04 / 10 add their entity sub-accounts and route the posting actions (a master with children is rejected by `AccountResolver`, so it can only flip in the same slice).
- [x] Every `AccountCode` case resolves via `AccountResolver` (`ChartOfAccountsV2Test::test_every_account_code_resolves_to_a_seeded_account`)
- [x] `JournalService::record()` rejects a direct post to every non-postable master (`ChartOfAccountsV2Test::test_every_non_postable_master_is_flagged_and_rejected_by_journal_service`)
- [x] `AccountsSeeder` updated. `database/seeders/Historical/*` reference **no** account codes (they drive the AutoPost actions) so they reproduce v2.0 balances transitively — no edit needed.
- [x] `docs/accounts-map.md` updated (v2.0 delta section + trigger map). `specs/001-eye-hospital-hms/contracts/accounting.md` auto-posting section refreshed + pointed at `accounts-map.md`.
- [x] Migration reversibility test: `ChartOfAccountsV2Test::test_renumber_migration_preserves_every_balance_and_keeps_the_ledger_footed`
- [x] `vendor/bin/pint --dirty` clean; affected tests green (303 pass; 2 pre-existing unrelated failures)
