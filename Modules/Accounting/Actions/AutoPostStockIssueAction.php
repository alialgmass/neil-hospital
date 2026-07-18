<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;

class AutoPostStockIssueAction
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Post journal entries for each item in a stock issue voucher.
     * Dr [expense account by item category] / Cr 1050 (Inventory)
     */
    public function execute(StockPermit $permit): void
    {
        $inventoryAccountId = DB::table('accounts')->where('code', '1050')->value('id');

        if (! $inventoryAccountId) {
            return;
        }

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

            $expenseCode = $this->expenseAccountCode($category);
            $expenseAccountId = DB::table('accounts')->where('code', $expenseCode)->value('id');

            if (! $expenseAccountId) {
                continue;
            }

            $this->journalService->record([
                'date' => $date,
                'description' => "صرف مخزون: {$item->item_name} — إذن رقم {$permit->permit_no}",
                'debit_account_id' => $expenseAccountId,
                'credit_account_id' => $inventoryAccountId,
                'amount' => $amount,
                'source' => JournalSource::SUPPLIES_USED,
                'reference' => $permit->permit_no,
                'cost_center' => $costCenter,
            ]);
        }
    }

    private function expenseAccountCode(?string $category): string
    {
        return match ($category) {
            'office' => '5250', // مصروفات إدارية وتسويقية
            'cleaning' => '5240', // مصروفات الصيانة
            'maintenance' => '5240', // مصروفات الصيانة
            default => '5010', // تكلفة مستلزمات طبية
        };
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
