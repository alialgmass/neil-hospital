# 07: Insurance same-day costs (doctor cash + consumables)

**What to build:** On the service day of an insurance case, the insurance doctor is paid their flat per-case fee in cash — debited straight to 5130 against the main treasury (1010), never touching the doctor payable account (2010) — and the consumables cost is relieved from inventory (Dr 5010 / Cr 1050). On rejection the doctor's cash fee is reversed only when the agreement makes it contingent on claim acceptance; the consumables entry is never reversed (the supplies were really consumed).

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] New `AutoPostInsuranceDoctorCashPaymentAction`: Dr 5130 / Cr 1010 for the flat fee from the service definition, tagged CC-INS, on the service day; 2010 untouched
- [ ] Consumables cost for the insurance case posted Dr 5010 / Cr 1050 at purchase price on the service day
- [ ] Both idempotent per claim
- [ ] `onReject`: reverse the 5130 cash fee only when a contingent-fee flag/config is set; never reverse the consumables entry
- [ ] TDD at the action seam: assert 2010 has zero movement, 5130 + treasury move, inventory relieved; reject with and without the contingent flag
- [ ] Guide worked example reproduces: 6,500 ج cataract case — 5130 +500, 1010 −500, 5010 +2,000, 1051 −2,000, 2010 = 0
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
