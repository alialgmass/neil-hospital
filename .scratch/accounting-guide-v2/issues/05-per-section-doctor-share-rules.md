# 05: Per-section doctor-share rules

**What to build:** Each doctor's share is calculated by the exact rule for their department (الدليل §2.7), on the post-development-fund base (`paid − 50`), and always booked as an independent expense — revenue is never netted against it. The same rule is used at payment time (per-installment) and in the aggregate claims report (whole booking).

**Blocked by:** 01, 02, 03, 04

**Status:** ready-for-agent

Rules (base = amount actually paid − 50 ج development fund, when a deduction applied):

| Department | Doctor share | Center revenue | Expense acct |
|---|---|---|---|
| Clinic | 50% × base | remaining 50% | 5110 |
| Labs | per-doctor % × base | remainder | 5110 |
| Laser | base − fixed center revenue for the service | fixed price-list amount | 5110 |
| Pentacam | none (no posting) | full paid amount | — |
| Lasik | base − consumables at sale price (5120 at full base; consumables via 5115) | consumables sale price | 5120 |
| Surgery cash | base − consumables at sale price (5120 at full base; consumables via 5115) | consumables sale price | 5120 |
| Insurance surgery/lasik | flat per-case fee (service definition), cash on service day | remainder of claim value | 5130 |

- [ ] `DoctorClaimsService::computeShareForPayment` and `computeDrShare` both implement the table on the `paid − 50` base and agree with each other
- [ ] Labs requires `doctor_id` on the booking; per-doctor % from `doctor.dept_fees['labs']`; missing → configured default + log, never a silent wrong share
- [ ] Laser: patient discount reduces the doctor's remainder first; center fixed revenue unaffected unless a per-agreement override says otherwise
- [ ] Clinic/labs %: applied to the amount actually paid so discounts flow through proportionally
- [ ] Flat per-case fees attributed to the first installment; percentage fees prorated across installments
- [ ] `AutoPostDoctorDuesAction` posts the share to the right expense account (5110/5120), tagged with the service's cost center; pentacam posts nothing
- [ ] TDD at the `DoctorClaimsService` + `AutoPostDoctorDuesAction` seam: one test per department; extend `DoctorClaimsReportTest`
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
