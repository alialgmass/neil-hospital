<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Models\PurchaseInvoice;

class AutoPostPurchaseInvoiceAction
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Post when a purchase invoice is received.
     *
     * Cash purchase:   Dr 1050 (Inventory)  / Cr 1010 (Cash)
     * Credit purchase: Dr 1050 (Inventory)  / Cr 2020 (Suppliers)
     */
    public function execute(PurchaseInvoice $invoice): void
    {
        $total = (float) $invoice->total;

        if ($total <= 0) {
            return;
        }

        $inventoryId = DB::table('accounts')->where('code', '1050')->value('id');

        // Fully paid → debit cash; any credit remaining → debit suppliers
        $isCash = (float) $invoice->paid_amount >= $total;
        $creditCode = $isCash ? '1010' : '2020';
        $creditId = DB::table('accounts')->where('code', $creditCode)->value('id');

        if (! $inventoryId || ! $creditId) {
            return;
        }

        $supplierName = DB::table('suppliers')->where('id', $invoice->supplier_id)->value('name') ?? 'مورد';

        $this->journalService->record([
            'date' => $invoice->invoice_date->toDateString(),
            'description' => "فاتورة شراء {$invoice->invoice_no} — {$supplierName}",
            'debit_account_id' => $inventoryId,
            'credit_account_id' => $creditId,
            'amount' => $total,
            'source' => JournalSource::PURCHASE,
            'reference' => $invoice->invoice_no,
            'cost_center' => CostCenter::Inventory,
        ]);
    }
}
