<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Accounting\Services\TreasuryService;
use Modules\Inventory\Models\SupplierPayment;

class AutoPostSupplierPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Post when a supplier is paid.
     * Dr the supplier's sub-ledger (2301–2399) / Cr 1010 (Cash) or 1020 (Bank), by payment method.
     */
    public function execute(SupplierPayment $payment, string $supplierName): void
    {
        $amount = (float) $payment->amount;

        if ($amount <= 0) {
            return;
        }

        $date = $payment->paid_at?->toDateString() ?? now()->toDateString();
        $isBank = $payment->method === 'transfer';

        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => "سداد مورد: {$supplierName}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::SUPPLIER_PAYMENT,
        ]);

        $suppliersId = $this->subledgers->forSupplier($payment->supplier_id);
        $creditId = $this->accountResolver->id($isBank ? AccountCode::BANK : AccountCode::CASH);

        $this->journalService->record([
            'date' => $date,
            'description' => "سداد مستحقات مورد: {$supplierName} — VCH-{$payment->id}",
            'debit_account_id' => $suppliersId,
            'credit_account_id' => $creditId,
            'amount' => $amount,
            'source' => JournalSource::SUPPLIER_PAYMENT,
            'reference' => (string) $payment->id,
            'idempotency_key' => "supplier_payment:{$payment->id}",
            'cost_center' => CostCenter::Inventory,
        ]);
    }
}
