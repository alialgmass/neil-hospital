<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Services\GuideAlignmentService;

/**
 * Guide v2.0 alignment, phase 1: chart of accounts = the guide (codes, names,
 * natures, control accounts 2010/2020/2030 non-postable, named sub-ledgers
 * 2201–2235 / 2301–2324 / 2401–2416) and every doctor / supplier / employee
 * linked to its own sub-ledger account.
 *
 * Reversible: every change is recorded in accounting_alignment_log under
 * this migration's batch and undone exactly by down(). The trial balance is
 * re-checked (debits = credits) before the phase commits, in both directions.
 */
return new class extends Migration
{
    private const BATCH = 'guide_v2_chart';

    public function up(): void
    {
        app(GuideAlignmentService::class)->alignChart(self::BATCH);
    }

    public function down(): void
    {
        app(GuideAlignmentService::class)->revert(self::BATCH);
    }
};
