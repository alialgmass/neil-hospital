<?php

namespace Modules\Inventory\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostItemSaleAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\ItemSalesInvoice;

class CreateItemSalesInvoiceAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostItemSaleAction $autoPost,
    ) {}

    /**
     * Sell inventory items at the entered unit price: deduct stock, record the
     * invoice with each line's current unit cost, and post revenue + cost.
     *
     * @param  array{invoice_date: string, customer_name: string, customer_phone?: ?string, file_no?: ?string, department?: ?string, pay_method: string, discount?: ?float, notes?: ?string}  $data
     * @param  array<int, array{item_id: string, qty: float, unit_price: float}>  $items
     */
    public function execute(array $data, array $items): ItemSalesInvoice
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->attempt($data, $items);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::MAX_ATTEMPTS || ! str_contains($e->getMessage(), 'invoice_no')) {
                    throw ValidationException::withMessages([
                        'items' => 'تعذر إصدار رقم الفاتورة بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
                    ]);
                }
            }
        }

        throw ValidationException::withMessages([
            'items' => 'تعذر إصدار رقم الفاتورة بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{item_id: string, qty: float, unit_price: float}>  $items
     */
    private function attempt(array $data, array $items): ItemSalesInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $lines = $this->buildLines($items);

            $subtotal = round($lines->sum('line_total'), 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => 'الخصم أكبر من إجمالي الفاتورة.',
                ]);
            }

            $invoice = ItemSalesInvoice::create([
                ...$data,
                'invoice_no' => $this->generateInvoiceNo(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => round($subtotal - $discount, 2),
                'cost_total' => round($lines->sum(fn (array $line) => $line['qty'] * $line['unit_cost']), 2),
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create($line);
                InventoryItem::whereKey($line['item_id'])->decrement('quantity', $line['qty']);
            }

            $this->autoPost->execute($invoice->load('items'));

            $this->activityLogService->log(
                action: 'create',
                module: 'inventory',
                recordId: $invoice->id,
                description: "فاتورة بيع أصناف رقم {$invoice->invoice_no}",
            );

            return $invoice;
        });
    }

    /**
     * Lock each sold item, check the combined requested quantity against
     * stock, and snapshot its name and current unit cost onto the line.
     *
     * @param  array<int, array{item_id: string, qty: float, unit_price: float}>  $items
     * @return Collection<int, array{item_id: string, item_name: string, qty: float, unit_price: float, unit_cost: float, line_total: float}>
     */
    private function buildLines(array $items): Collection
    {
        $requestedByItem = collect($items)
            ->groupBy('item_id')
            ->map(fn ($lines) => $lines->sum(fn (array $line) => (float) $line['qty']));

        $inventoryItems = InventoryItem::whereKey($requestedByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requestedByItem as $itemId => $requestedQty) {
            $inventoryItem = $inventoryItems->get($itemId);

            if (! $inventoryItem || (float) $inventoryItem->quantity < $requestedQty) {
                throw ValidationException::withMessages([
                    'items' => "الكمية المطلوبة من {$inventoryItem?->name} ({$requestedQty}) أكبر من الرصيد المتاح ({$inventoryItem?->quantity})",
                ]);
            }
        }

        return collect($items)->map(function (array $item) use ($inventoryItems) {
            $inventoryItem = $inventoryItems->get($item['item_id']);
            $qty = (float) $item['qty'];
            $unitPrice = (float) $item['unit_price'];

            return [
                'item_id' => $inventoryItem->id,
                'item_name' => $inventoryItem->name,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'unit_cost' => (float) $inventoryItem->unit_cost,
                'line_total' => round($qty * $unitPrice, 2),
            ];
        });
    }

    private function generateInvoiceNo(): string
    {
        $prefix = 'SAL-'.date('Y').'-';

        $last = ItemSalesInvoice::where('invoice_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
