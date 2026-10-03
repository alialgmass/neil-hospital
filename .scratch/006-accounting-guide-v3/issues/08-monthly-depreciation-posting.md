# 08: Monthly depreciation posting

**What to build:** A new `AutoPostDepreciationAction`, runnable monthly (scheduled Artisan command, consistent with this codebase having no existing Jobs/observer infrastructure), that posts `Dr 526x (depreciation expense, by asset class) / Cr 11x1 (accumulated depreciation, contra-asset)` for each depreciable fixed asset, computed from cost, useful life, and accumulated depreciation to date. Never modifies the asset's original cost.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Confirm whether a fixed-asset table/model already exists in the codebase; if not, add a minimal one (cost, useful_life, asset_class, accumulated_depreciation, acquired_at) — no broader fixed-asset-register features.
- [ ] `AutoPostDepreciationAction` posts the correct expense/contra-asset account pair per asset class (buildings/medical equipment/furniture/computers/vehicles).
- [ ] Idempotent per asset per month (re-running the same month never double-posts).
- [ ] Asset's original cost field is never written by this action.
- [ ] Test: one asset per class posts the correct pair and amount for a given month.
- [ ] Test: running the same month twice does not create a duplicate journal entry.
