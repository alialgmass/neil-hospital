<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\InvoiceStatus;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;

class PurchaseInvoiceService
{
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return PurchaseInvoice::query()
            ->with(['supplier', 'creator'])
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('invoice_date', '<=', $v))
            ->orderByDesc('invoice_date')
            ->paginate($perPage);
    }

    public function create(array $data, array $items): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn ($i) => $i['qty'] * $i['unit_cost']);
            $discount = (float) ($data['discount'] ?? 0);
            $total = $subtotal - $discount;
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            $remaining = $total - $paidAmount;

            $status = InvoiceStatus::Partial;
            if ($remaining <= 0) {
                $status = InvoiceStatus::Paid;
            } elseif ($paidAmount <= 0) {
                $status = InvoiceStatus::Unpaid;
            }

            $invoice = PurchaseInvoice::create([
                ...$data,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'remaining' => $remaining,
                'status' => $status,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'item_id' => $item['item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'total' => $item['qty'] * $item['unit_cost'],
                ]);

                // Update stock quantity
                if (! empty($item['item_id'])) {
                    InventoryItem::where('id', $item['item_id'])->increment('quantity', $item['qty']);
                }
            }

            // Update supplier balance
            if ($invoice->supplier_id) {
                Supplier::where('id', $invoice->supplier_id)->increment('balance', $invoice->remaining);
            }

            return $invoice->load(['items.inventoryItem', 'supplier']);
        });
    }

    public function getActiveSuppliers()
    {
        return Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
