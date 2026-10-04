<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Booking\Enums\PayMethod;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\ItemSalesInvoice;

class AutoPostItemSaleAction
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post a paid item sales invoice:
     * - Revenue: Dr 1010 cash / 1020 bank — Cr 4210 (net of discount) + treasury inflow.
     * - Cost:    Dr consumption cost account — Cr 1051, per line at its unit cost.
     */
    public function execute(ItemSalesInvoice $invoice): void
    {
        $date = $invoice->invoice_date->toDateString();
        $costCenter = CostCenter::forDepartment($invoice->department);
        $total = round((float) $invoice->total, 2);

        if ($total > 0) {
            $this->treasuryService->record([
                'type' => TreasuryType::In,
                'description' => "فاتورة بيع أصناف: {$invoice->invoice_no} — {$invoice->customer_name}",
                'amount' => $total,
                'date' => $date,
                'source' => JournalSource::ITEM_SALE,
                'reference_no' => $invoice->invoice_no,
            ]);

            $this->journalService->record([
                'date' => $date,
                'description' => "إيراد بيع أصناف: فاتورة {$invoice->invoice_no} — {$invoice->customer_name}",
                'debit_account_id' => $this->accountResolver->id($this->debitAccountCode($invoice->pay_method)),
                'credit_account_id' => $this->accountResolver->id(AccountCode::SUPPLIES_REVENUE),
                'amount' => $total,
                'source' => JournalSource::ITEM_SALE,
                'reference' => $invoice->invoice_no,
                'idempotency_key' => "item_sale:{$invoice->id}",
                'cost_center' => $costCenter,
            ]);
        }

        $inventoryAccountId = $this->accountResolver->id(AccountCode::INVENTORY);

        foreach ($invoice->items as $item) {
            $cost = round((float) $item->qty * (float) $item->unit_cost, 2);

            if ($cost <= 0) {
                continue;
            }

            $category = $item->item_id
                ? InventoryItem::find($item->item_id, ['id', 'category'])?->category
                : null;

            $this->journalService->record([
                'date' => $date,
                'description' => "تكلفة بيع: {$item->item_name} — فاتورة {$invoice->invoice_no}",
                'debit_account_id' => $this->accountResolver->id(AccountCode::consumptionCostCode($invoice->department, $category)),
                'credit_account_id' => $inventoryAccountId,
                'amount' => $cost,
                'source' => JournalSource::ITEM_SALE,
                'reference' => $invoice->invoice_no,
                'idempotency_key' => "item_sale_cost:{$invoice->id}:{$item->id}",
                'cost_center' => $costCenter,
            ]);
        }
    }

    private function debitAccountCode(PayMethod $payMethod): AccountCode
    {
        return match ($payMethod) {
            PayMethod::Card, PayMethod::Transfer => AccountCode::BANK,
            default => AccountCode::CASH,
        };
    }
}
