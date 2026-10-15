<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Inventory\Models\PurchaseInvoice;

class AutoPostPurchaseReturnAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Post when goods are returned to a supplier against a purchase invoice.
     *
     * If the original invoice was credit-purchased: Dr supplier sub-ledger (2301–2399) / Cr 1051 (Inventory)
     * If the original invoice was cash-purchased:    Dr 1010 (Cash)                     / Cr 1051 (Inventory)
     */
    public function execute(PurchaseInvoice $invoice, float $returnTotal): void
    {
        if ($returnTotal <= 0) {
            return;
        }

        $inventoryId = $this->accountResolver->id(AccountCode::INVENTORY);

        $isCash = (float) $invoice->paid_amount >= (float) $invoice->total;
        // A credit invoice always has a supplier (enforced when it was posted).
        $debitId = $isCash || ! $invoice->supplier_id
            ? $this->accountResolver->id(AccountCode::CASH)
            : $this->subledgers->forSupplier($invoice->supplier_id);

        $supplierName = DB::table('suppliers')->where('id', $invoice->supplier_id)->value('name') ?? 'مورد';

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => JournalNarration::make('مرتجع مشتريات', [
                'فاتورة' => $invoice->invoice_no,
                'المورد' => $supplierName,
                'قيمة المرتجع' => JournalNarration::money($returnTotal),
                'إجمالي الفاتورة' => JournalNarration::money($invoice->total),
                'التسوية' => $debitId === $this->accountResolver->id(AccountCode::CASH) ? 'استرداد نقدي' : 'خصم من حساب المورد',
            ]),
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
