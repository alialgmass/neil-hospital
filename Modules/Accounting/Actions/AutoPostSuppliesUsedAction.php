<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;

class AutoPostSuppliesUsedAction
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Post when medical supplies are consumed in a procedure.
     * Dr 5010 (Surgery) or 5020 (Lasik) / Cr 1050 (Inventory)
     */
    public function execute(
        Department $dept,
        float $totalCost,
        string $description,
        string $reference,
        ?string $date = null,
    ): void {
        if ($totalCost <= 0) {
            return;
        }

        $expenseCode = match ($dept) {
            Department::Lasik => '5020',
            default => '5010',
        };

        $expenseId = DB::table('accounts')->where('code', $expenseCode)->value('id');
        $inventoryId = DB::table('accounts')->where('code', '1050')->value('id');

        if (! $expenseId || ! $inventoryId) {
            return;
        }

        $costCenter = match ($dept) {
            Department::Lasik => CostCenter::Lasik,
            Department::Surgery => CostCenter::Surgery,
            default => CostCenter::Surgery,
        };

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => $description,
            'debit_account_id' => $expenseId,
            'credit_account_id' => $inventoryId,
            'amount' => $totalCost,
            'source' => JournalSource::SUPPLIES_USED,
            'reference' => $reference,
            'cost_center' => $costCenter,
        ]);
    }
}
