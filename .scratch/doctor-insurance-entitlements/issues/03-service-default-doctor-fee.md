# 03: Service default doctor fee

**What to build:** An admin sees and edits an "أتعاب الطبيب (افتراضي)" field on the service create/edit screen. This value is the fallback doctor fee when a doctor has no per-service fee configured.

**Note:** `services.dr_share` is NOT reusable — `CreateServiceAction`/`UpdateServiceAction::computeShares()` always overwrite it with `price − center_share` (the doctor's portion of the price, not a flat fee). Add a dedicated nullable `default_dr_fee` column instead.

**Blocked by:** None (can start immediately)

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `services.default_dr_fee` nullable decimal column added by migration
- [ ] Service store/update requests accept `default_dr_fee` (nullable, numeric, ≥ 0)
- [ ] Create/Update service actions persist `default_dr_fee` and do NOT let `computeShares()` touch it
- [ ] `admin/Services.vue` create and edit forms expose the field labelled "أتعاب الطبيب (افتراضي)"
- [ ] Existing service feature tests stay green
