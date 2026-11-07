# 09: Monthly automated depreciation

**What to build:** Fixed assets depreciate automatically every month. A scheduled monthly run posts, per asset, a debit to the depreciation expense sub-account for its class (5261 buildings, 5262 medical equipment, 5263 furniture, 5264 computers, 5265 vehicles) and a credit to the matching contra-accumulated-depreciation account (1121/1131/1141/1151/1161). Each asset's monthly amount is derived from its cost and useful life. The run is idempotent per asset per month.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] Minimal `fixed_assets` representation: cost, class, useful life, in-service date, operating cost center (add a table if none exists)
- [ ] `AutoPostDepreciationAction` + a scheduled monthly Artisan command (follow existing AutoPost action shape + Laravel scheduling conventions)
- [ ] Monthly amount from cost ÷ useful-life-in-months per class (buildings ~50y, medical equipment 5–10y, furniture 8–10y, computers 4–5y, vehicles 5y)
- [ ] Dr 5261–5265 / Cr 1121–1161; medical-equipment line (5262) taggable to the operating cost center
- [ ] Idempotent per asset per month (`depreciation:{asset}:{YYYY-MM}`)
- [ ] TDD at the action seam: run for a month, assert per-class expense + contra-account credit; re-run is a no-op
- [ ] `vendor/bin/pint --dirty` clean; affected tests green
