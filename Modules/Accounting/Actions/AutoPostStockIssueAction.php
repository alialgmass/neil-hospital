<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
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
     * Post journal entries for each item in a stock issue voucher.
     * Dr [expense account by item category] / Cr 1050 (Inventory)
     */
    public function execute(StockPermit $permit): void
    {
        $inventoryAccountId = $this->accountResolver->id(AccountCode::INVENTORY);
        $costCenter = $this->resolveCostCenter($permit->department);
        $date = $permit->created_at->toDateString();

        foreach ($permit->items as $item) {
            $amount = round((float) $item->qty * (float) $item->unit_cost, 2);

            if ($amount <= 0) {
                continue;
            }

            $category = $item->item_id
                ? InventoryItem::where('id', $item->item_id)->value('category')
                : null;

            $expenseAccountId = $this->accountResolver->id(AccountCode::expenseAccountForCategory($category));

            $this->journalService->record([
                'date' => $date,
                'description' => "صرف مخزون: {$item->item_name} — إذن رقم {$permit->permit_no}",
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

    private function resolveCostCenter(?Department $department): CostCenter
    {
        if ($department === null) {
            return CostCenter::Inventory;
        }

        return match ($department) {
            Department::Clinic => CostCenter::Clinic,
            Department::Labs => CostCenter::Lab,
            Department::Surgery => CostCenter::Surgery,
            Department::Lasik => CostCenter::Lasik,
            Department::Laser => CostCenter::Laser,
        };
    }
}
