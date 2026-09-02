<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostPurchaseInvoiceAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Enums\InvoiceStatus;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly AutoPostPurchaseInvoiceAction $autoPost,
        private readonly JournalService $journalService,
    ) {}

    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return PurchaseInvoice::query()
            ->with(['supplier', 'creator', 'items'])
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

    /**
     * Update an invoice's header and line items. Reverses the original
     * items' stock impact before applying the new ones, adjusts the
     * supplier balance by the delta, and reverses + reposts accounting if
     * the invoice had already been journaled.
     *
     * @throws ValidationException if reducing a line's quantity below what
     *                             has already been consumed from stock.
     */
    public function update(string $id, array $data, array $items): PurchaseInvoice
    {
        return DB::transaction(function () use ($id, $data, $items) {
            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::with('items')->lockForUpdate()->findOrFail($id);
            $oldRemaining = (float) $invoice->remaining;

            $this->reverseItemQuantities($invoice);
            $invoice->items()->delete();

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

            $invoice->update([
                'invoice_no' => $data['invoice_no'] ?? $invoice->invoice_no,
                'supplier_id' => $data['supplier_id'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining' => $remaining,
                'status' => $status,
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'item_id' => $item['item_id'] ?? null,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'total' => $item['qty'] * $item['unit_cost'],
                ]);

                if (! empty($item['item_id'])) {
                    $this->applyItemQuantity($item['item_id'], (float) $item['qty']);
                }
            }

            if ($invoice->supplier_id) {
                Supplier::where('id', $invoice->supplier_id)->increment('balance', $remaining - $oldRemaining);
            }

            $this->reverseAndRepostAccounting($invoice->fresh());

            return $invoice->fresh()->load(['items.inventoryItem', 'supplier']);
        });
    }

    /**
     * Delete an invoice: reverses its stock and accounting impact first,
     * then removes the invoice and its line items.
     *
     * @throws ValidationException if the invoice's received stock has
     *                             already been partly consumed below its
     *                             received quantities.
     */
    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            /** @var PurchaseInvoice $invoice */
            $invoice = PurchaseInvoice::with('items')->lockForUpdate()->findOrFail($id);

            $this->reverseItemQuantities($invoice);

            if ($invoice->supplier_id) {
                Supplier::where('id', $invoice->supplier_id)->decrement('balance', (float) $invoice->remaining);
            }

            $this->reverseJournalEntry($invoice);

            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    /**
     * Subtract each line's received quantity back out of stock, refusing if
     * that would push any item's quantity negative (i.e. some of it has
     * already been consumed/sold below the received amount).
     */
    private function reverseItemQuantities(PurchaseInvoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            if (! $item->item_id) {
                continue;
            }

            $inventoryItem = InventoryItem::whereKey($item->item_id)->lockForUpdate()->first();

            if (! $inventoryItem) {
                continue;
            }

            if ((float) $inventoryItem->quantity - (float) $item->qty < 0) {
                throw ValidationException::withMessages([
                    'items' => "لا يمكن تعديل/حذف الفاتورة: كمية الصنف \"{$inventoryItem->name}\" المتبقية أقل من الكمية المستلمة في هذه الفاتورة (تم صرف جزء منها).",
                ]);
            }

            $inventoryItem->decrement('quantity', (float) $item->qty);
        }
    }

    private function applyItemQuantity(string $itemId, float $qty): void
    {
        InventoryItem::whereKey($itemId)->increment('quantity', $qty);
    }

    /**
     * Reverse the invoice's original journal entry (if any and not already
     * reversed) and post a fresh one for its current total.
     */
    private function reverseAndRepostAccounting(PurchaseInvoice $invoice): void
    {
        $this->reverseJournalEntry($invoice);

        if ((float) $invoice->total > 0) {
            $this->autoPost->execute($invoice);
        }
    }

    private function reverseJournalEntry(PurchaseInvoice $invoice): void
    {
        $entry = JournalEntry::where('source', JournalSource::PURCHASE)
            ->where('idempotency_key', "purchase_invoice:{$invoice->id}")
            ->whereNull('reversed_at')
            ->first();

        if ($entry) {
            $this->journalService->reverse(
                $entry,
                JournalSource::REVERSAL,
                $invoice->invoice_no,
                "عكس قيد فاتورة شراء: {$invoice->invoice_no}",
            );

            // Free the idempotency key: a reversed entry no longer represents
            // the invoice's posted state, so a repost (on edit) must be able
            // to reuse "purchase_invoice:{id}" instead of being short-circuited
            // by JournalService::record()'s idempotency lookup finding this row.
            $entry->update(['idempotency_key' => null]);
        }
    }
}
