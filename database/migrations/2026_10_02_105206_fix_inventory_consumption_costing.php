<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Services\GuideAlignmentService;

/**
 * Guide v2.0 alignment, phase 5: inventory consumption is costed to the
 * consuming department's account (5010 / 5020 / 5030 / 5040); consumption lines
 * not costed at all are flagged for review.
 *
 * Reversible: every change is recorded in accounting_alignment_log under
 * this migration's batch and undone exactly by down(). The trial balance is
 * re-checked (debits = credits) before the phase commits, in both directions.
 */
return new class extends Migration
{
    private const BATCH = 'guide_v2_inventory_costing';

    public function up(): void
    {
        app(GuideAlignmentService::class)->fixInventoryConsumptionCosting(self::BATCH);
    }

    public function down(): void
    {
        app(GuideAlignmentService::class)->revert(self::BATCH);
    }
};
