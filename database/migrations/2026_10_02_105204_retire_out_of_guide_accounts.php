<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Services\GuideAlignmentService;

/**
 * Guide v2.0 alignment, phase 3: accounts outside the guide are retired —
 * 4065 (retina) → 4030 tagged CC-SURG, 4080 → 4210, 5115 → 4070 — after their
 * history and service revenue overrides move to the successor.
 *
 * Reversible: every change is recorded in accounting_alignment_log under
 * this migration's batch and undone exactly by down(). The trial balance is
 * re-checked (debits = credits) before the phase commits, in both directions.
 */
return new class extends Migration
{
    private const BATCH = 'guide_v2_retire_accounts';

    public function up(): void
    {
        app(GuideAlignmentService::class)->retireOutOfGuideAccounts(self::BATCH);
    }

    public function down(): void
    {
        app(GuideAlignmentService::class)->revert(self::BATCH);
    }
};
