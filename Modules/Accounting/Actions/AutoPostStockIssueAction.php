<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;

class AutoPostStockIssueAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post journal entries for each item in a stock issue voucher, at cost.
     * Dr [consuming department's cost account — 5010 / 5020 / 5030 / 5040,
     *     or the operating-supplies expense for non-medical items] / Cr 1051
     * Inventory never leaves the books without its cost being recorded.
     */
    public function execute(StockPermit $permit): void
    {
        $inventoryAccountId = $this->accountResolver->id(AccountCode::INVENTORY);
        $costCenter = CostCenter::forDepartment($permit->department);
        $date = $permit->created_at->toDateString();

        foreach ($permit->items as $item) {
            $amount = round((float) $item->qty * (float) $item->unit_cost, 2);

            if ($amount <= 0) {
                continue;
            }

            $category = $item->item_id
                ? InventoryItem::find($item->item_id, ['id', 'category'])?->category
                : null;

            $expenseAccountId = $this->accountResolver->id(AccountCode::consumptionCostCode($permit->department, $category));

            $this->journalService->record([
                'date' => $date,
                'description' => JournalNarration::make('صرف مخزون', [
                    'الصنف' => $item->item_name,
                    'الكمية' => (float) $item->qty,
                    'تكلفة الوحدة' => JournalNarration::money($item->unit_cost),
                    'الإجمالي' => JournalNarration::money($amount),
                    'إذن رقم' => $permit->permit_no,
                    'القسم' => $permit->department,
                    'السبب' => $permit->reason,
                ]),
                'debit_account_id' => $expenseAccountId,
                'credit_account_id' => $inventoryAccountId,
                'amount' => $amount,
                'source' => JournalSource::SUPPLIES_USED,
                'reference' => $permit->permit_no,
                'idempotency_key' => "stock_issue:{$permit->id}:{$item->id}",
                'cost_center' => $costCenter,
            ]);
        }
    }
}
