<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;

class PurchaseReturnService
{
    public function list(int $perPage = 20): LengthAwarePaginator
    {
        return PurchaseInvoice::query()
            ->with('supplier')
            ->where('status', 'cancelled')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getEligibleInvoices(): Collection
    {
        return PurchaseInvoice::with(['supplier', 'items'])
            ->where('status', 'posted')
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_no', 'invoice_date', 'total', 'supplier_id']);
    }

    public function processReturn(array $data): void
    {
        DB::transaction(function () use ($data) {
            $invoice = PurchaseInvoice::findOrFail($data['invoice_id']);

            foreach ($data['items'] as $item) {
                if (! empty($item['item_id'])) {
                    // Deduct returned qty from inventory
                    InventoryItem::where('id', $item['item_id'])
                        ->decrement('quantity', $item['qty']);
                }
            }

            // Reduce supplier balance by return value
            $returnTotal = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['unit_cost']);

            if ($invoice->supplier_id) {
                Supplier::where('id', $invoice->supplier_id)->decrement('balance', $returnTotal);
            }

            // Mark invoice as returned or cancelled if needed
            // Currently the controller doesn't update the invoice status itself,
            // but the list filters by status='cancelled'.
            // This might be a bug in the original code where it doesn't set status to cancelled.
            $invoice->update(['status' => 'cancelled']);
        });
    }
}
