<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;

class AutoPostSupplierPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
    ) {}

    /**
     * Post when a supplier is paid.
     * Dr 2020 (Suppliers) / Cr 1010 (Cash)
     */
    public function execute(
        float $amount,
        string $supplierName,
        string $reference,
        ?string $date = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $date ??= now()->toDateString();

        // Treasury outflow
        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => "سداد مورد: {$supplierName}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::SUPPLIER_PAYMENT,
        ]);

        $suppliersId = DB::table('accounts')->where('code', '2020')->value('id');
        $cashId = DB::table('accounts')->where('code', '1010')->value('id');

        if (! $suppliersId || ! $cashId) {
            return;
        }

        $this->journalService->record([
            'date' => $date,
            'description' => "سداد مستحقات مورد: {$supplierName}",
            'debit_account_id' => $suppliersId,
            'credit_account_id' => $cashId,
            'amount' => $amount,
            'source' => JournalSource::SUPPLIER_PAYMENT,
            'reference' => $reference,
            'cost_center' => CostCenter::Inventory,
        ]);
    }
}
