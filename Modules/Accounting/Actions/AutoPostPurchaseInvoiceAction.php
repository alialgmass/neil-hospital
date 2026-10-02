<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Inventory\Models\PurchaseInvoice;

class AutoPostPurchaseInvoiceAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Post when a purchase invoice is received.
     *
     * Cash purchase:   Dr 1051 (Inventory)  / Cr 1010 (Cash)
     * Credit purchase: Dr 1051 (Inventory)  / Cr the supplier's sub-ledger (2301–2399, under 2020)
     */
    public function execute(PurchaseInvoice $invoice): void
    {
        $total = (float) $invoice->total;

        if ($total <= 0) {
            return;
        }

        $inventoryId = $this->accountResolver->id(AccountCode::INVENTORY);

        // Fully paid → credit cash; any credit remaining → credit suppliers
        $isCash = (float) $invoice->paid_amount >= $total;
        if (! $isCash && ! $invoice->supplier_id) {
            throw ValidationException::withMessages([
                'supplier_id' => 'فاتورة الشراء الآجلة لازم يكون ليها مورد — المستحق بيتسجل على حساب المورد التفصيلي.',
            ]);
        }

        $creditId = $isCash
            ? $this->accountResolver->id(AccountCode::CASH)
            : $this->subledgers->forSupplier($invoice->supplier_id);

        $supplierName = DB::table('suppliers')->where('id', $invoice->supplier_id)->value('name') ?? 'مورد';

        $this->journalService->record([
            'date' => $invoice->invoice_date->toDateString(),
            'description' => "فاتورة شراء {$invoice->invoice_no} — {$supplierName}",
            'debit_account_id' => $inventoryId,
            'credit_account_id' => $creditId,
            'amount' => $total,
            'source' => JournalSource::PURCHASE,
            'reference' => $invoice->invoice_no,
            'idempotency_key' => "purchase_invoice:{$invoice->id}",
            'cost_center' => CostCenter::Inventory,
        ]);
    }
}
