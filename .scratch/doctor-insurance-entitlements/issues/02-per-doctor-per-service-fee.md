# 02: Per-doctor per-service fee

**What to build:** An admin opens a doctor and manages a list of `الخدمة + أتعاب الطبيب لهذه الخدمة` rows — add a service with a fee, edit the fee, remove a service. Each doctor has at most one fee per service. The data persists in a new `doctor_service` pivot and is readable via `Doctor::feeForService()`.

**Blocked by:** None (can start immediately)

**Status:** DONE (uncommitted, awaiting manual review)

- [ ] `doctor_service` pivot table: `doctor_id`, `service_id`, `fee` (decimal, default 0), unique on (`doctor_id`, `service_id`)
- [ ] `Doctor::services()` belongsToMany relation with `withPivot('fee')`
- [ ] `Doctor::feeForService(Service|string): ?float` returns the pivot fee or null
- [ ] Doctor store/update requests accept a `services` array of `{service_id, fee}`; fees must be numeric ≥ 0
- [ ] Create/Update doctor actions sync the pivot (full replace on update)
- [ ] Doctor create/edit screen has a repeatable "خدمات الطبيب" sub-form (mirrors the existing `dept_fees` UI)
- [ ] Doctors with no service-fee rows keep working unchanged
