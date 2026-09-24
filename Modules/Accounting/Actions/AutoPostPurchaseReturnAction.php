<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Models\PurchaseInvoice;

class AutoPostPurchaseReturnAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post when goods are returned to a supplier against a purchase invoice.
     *
     * If the original invoice was credit-purchased: Dr 2020 (Suppliers) / Cr 1050 (Inventory)
     * If the original invoice was cash-purchased:    Dr 1010 (Cash)      / Cr 1050 (Inventory)
     */
    public function execute(PurchaseInvoice $invoice, float $returnTotal): void
    {
        if ($returnTotal <= 0) {
            return;
        }

        $inventoryId = $this->accountResolver->id(AccountCode::INVENTORY);

        $isCash = (float) $invoice->paid_amount >= (float) $invoice->total;
        $debitId = $this->accountResolver->id($isCash ? AccountCode::CASH : AccountCode::SUPPLIER_PAYABLE);

        $supplierName = DB::table('suppliers')->where('id', $invoice->supplier_id)->value('name') ?? 'مورد';

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => "مرتجع مشتريات — فاتورة {$invoice->invoice_no} — {$supplierName}",
            'debit_account_id' => $debitId,
            'credit_account_id' => $inventoryId,
            'amount' => round($returnTotal, 2),
            'source' => JournalSource::PURCHASE,
            'reference' => 'RET-'.$invoice->invoice_no,
            'idempotency_key' => "purchase_return:{$invoice->id}",
            'cost_center' => CostCenter::Inventory,
        ]);
    }
}
