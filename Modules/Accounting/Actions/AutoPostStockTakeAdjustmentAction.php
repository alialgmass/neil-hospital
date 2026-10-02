<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\StockPermit;

class AutoPostStockTakeAdjustmentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post a physical-count adjustment, valued at each item's unit cost
     * (guide appendix §2 — عجز / زيادة الجرد):
     *   shortage (physical < book): Dr 5010 cost of supplies (عجز جرد) / Cr 1051
     *   surplus  (physical > book): Dr 1051 / Cr 4220 miscellaneous revenue
     *
     * @param  array<int, array{item_id: string, item_name: string, variance: float, unit_cost: float, category: ?ItemCategory}>  $variances
     */
    public function execute(StockPermit $permit, array $variances): void
    {
        $inventoryId = $this->accountResolver->id(AccountCode::INVENTORY);
        $date = $permit->created_at->toDateString();

        foreach ($variances as $line) {
            $amount = round(abs($line['variance']) * $line['unit_cost'], 2);

            if ($amount <= 0) {
                continue;
            }

            $isShortage = $line['variance'] < 0;

            $this->journalService->record([
                'date' => $date,
                'description' => ($isShortage ? 'عجز جرد: ' : 'زيادة جرد: ')."{$line['item_name']} — {$permit->permit_no}",
                'debit_account_id' => $isShortage
                    ? $this->accountResolver->id(AccountCode::consumptionCostCode(null, $line['category']))
                    : $inventoryId,
                'credit_account_id' => $isShortage
                    ? $inventoryId
                    : $this->accountResolver->id(AccountCode::MISC_REVENUE),
                'amount' => $amount,
                'source' => JournalSource::SUPPLIES_USED,
                'reference' => $permit->permit_no,
                'idempotency_key' => "stock_take:{$permit->id}:{$line['item_id']}",
                'cost_center' => CostCenter::Inventory,
            ]);
        }
    }
}
