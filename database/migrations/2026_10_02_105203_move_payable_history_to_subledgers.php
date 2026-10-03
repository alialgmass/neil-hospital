<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Services\GuideAlignmentService;

/**
 * Guide v2.0 alignment, phase 2: historical lines on the control accounts
 * 2010 / 2020 / 2030 move to the owning party's sub-ledger (traced from the
 * source document); untraceable lines land on the "غير محدّد — للمراجعة"
 * bucket with needs_review = true.
 *
 * Reversible: every change is recorded in accounting_alignment_log under
 * this migration's batch and undone exactly by down(). The trial balance is
 * re-checked (debits = credits) before the phase commits, in both directions.
 */
return new class extends Migration
{
    private const BATCH = 'guide_v2_payable_history';

    public function up(): void
    {
        app(GuideAlignmentService::class)->movePayableHistoryToSubledgers(self::BATCH);
    }

    public function down(): void
    {
        app(GuideAlignmentService::class)->revert(self::BATCH);
    }
};
