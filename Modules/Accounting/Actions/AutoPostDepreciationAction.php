<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Carbon;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;

/**
 * Posts one month's straight-line depreciation for every active fixed
 * asset: Dr 526x (depreciation expense, by asset class) / Cr 11x1
 * (accumulated depreciation, contra-asset). Never writes to the asset's
 * original cost — only its running accumulated_depreciation.
 */
class AutoPostDepreciationAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * @param  string|null  $period  "YYYY-MM"; defaults to the current month.
     * @return int Number of assets an entry was actually posted for.
     */
    public function execute(?string $period = null): int
    {
        $period ??= now()->format('Y-m');
        $date = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        $posted = 0;

        FixedAsset::where('is_active', true)->each(function (FixedAsset $asset) use ($period, $date, &$posted) {
            $amount = $asset->monthlyDepreciationAmount();

            if ($amount <= 0) {
                return;
            }

            $expenseId = $this->accountResolver->id($asset->asset_class->depreciationExpenseCode());
            $accumulatedId = $this->accountResolver->id($asset->asset_class->accumulatedDepreciationCode());

            $entry = $this->journalService->record([
                'date' => $date,
                'description' => "إهلاك {$period}: {$asset->name}",
                'debit_account_id' => $expenseId,
                'credit_account_id' => $accumulatedId,
                'amount' => $amount,
                'source' => JournalSource::EXPENSE,
                'reference' => $asset->id,
                'idempotency_key' => "depreciation:{$asset->id}:{$period}",
                'cost_center' => CostCenter::Admin,
            ]);

            // Idempotent re-run: only bump accumulated_depreciation the first
            // time this period's entry is actually created, not on a no-op
            // replay that returned the already-existing entry.
            if ($entry->wasRecentlyCreated) {
                $asset->increment('accumulated_depreciation', $amount);
                $posted++;
            }
        });

        return $posted;
    }
}
