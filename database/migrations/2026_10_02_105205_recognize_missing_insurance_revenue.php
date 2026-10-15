<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Services\GuideAlignmentService;

/**
 * Guide v2.0 alignment, phase 4: every non-rejected insurance claim carries its
 * service-day revenue + receivable recognition (Dr 1031–1047 / Cr 4110–4150);
 * 5130 lines with no matching insurance revenue are flagged for review.
 *
 * Reversible: every change is recorded in accounting_alignment_log under
 * this migration's batch and undone exactly by down(). The trial balance is
 * re-checked (debits = credits) before the phase commits, in both directions.
 */
return new class extends Migration
{
    private const BATCH = 'guide_v2_insurance_revenue';

    public function up(): void
    {
        app(GuideAlignmentService::class)->recognizeMissingInsuranceRevenue(self::BATCH);
    }

    public function down(): void
    {
        app(GuideAlignmentService::class)->revert(self::BATCH);
    }
};
