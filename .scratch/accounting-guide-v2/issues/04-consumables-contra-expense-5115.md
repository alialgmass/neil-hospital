# 04: Consumables contra-expense (5115)

**What to build:** In surgery and lasik, consumables are relieved from inventory at purchase price to the cost-of-supplies expense, and charged to the doctor at sale price via a contra-expense account (5115) that reduces net doctor fees — never credited to a revenue account. The center's margin on consumables (sale − purchase) falls out of the postings automatically. The shared surgery/lasik consumables price list is seeded. The doctor charge is deducted once (first installment).

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] Shared surgery/lasik consumables price list seeded (فتح غرفة العمليات 1,000/45, ميثيل 500/300, عدسة لينة 700/350, كيراتوم 160/70 — sale/purchase)
- [ ] `ProcessBundleSupplyAction` posts: Dr 5010 (surgery) or 5020 (lasik) / Cr 1050 at purchase price; Dr 2010 / Cr 5115 at sale price
- [ ] The Dr 2010 / Cr 4230 line is removed; 4230 retired
- [ ] Consumables charge hits the first installment only, not every installment
- [ ] Cost center = the service's center
- [ ] TDD at the `ProcessBundleSupplyAction` seam (rewrite `ProcessBundleSupplyAccountingTest`): assert inventory relieved at cost, 5115 credited at sale price, net doctor payable reduced, center margin = sale − purchase
- [ ] `vendor/bin/pint --dirty` clean; affected tests green

**Note (from ticket 01):** the v2.0 chart is already seeded (5115, 4110-4150, per-payer master 1030, net-salary master 2030). This ticket additionally owns: seeding the entity sub-accounts under its master, flipping that master to non-postable, and routing its AutoPost action(s) through an entity->leaf resolver.
