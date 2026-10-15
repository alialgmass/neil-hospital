<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostStockIssueAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;

class UpdateStockPermitAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostStockIssueAction $autoPost,
    ) {}

    /**
     * Edit an issued stock permit: return the old lines to stock, reverse
     * their journal entries, then issue the new lines and post them at cost.
     * The permit keeps its number and original date.
     *
     * @param  array{department?: ?string, reason?: ?string, notes?: ?string}  $data
     * @param  array<int, array{item_id: string, item_name?: ?string, qty: float, unit_cost?: ?float}>  $items
     */
    public function execute(string $permitId, array $data, array $items): StockPermit
    {
        return DB::transaction(function () use ($permitId, $data, $items) {
            $permit = StockPermit::whereKey($permitId)->lockForUpdate()->firstOrFail();

            if ($permit->type !== PermitType::Out) {
                throw ValidationException::withMessages([
                    'items' => 'لا يمكن تعديل هذا الإذن من شاشة أذون الصرف.',
                ]);
            }

            $permit->load('items');

            $this->returnItemsToStock($permit);
            $this->reverseJournalEntries($permit);

            $this->guardAvailableQuantities($items);

            $permit->update($data);
            $permit->items()->delete();

            foreach ($items as $item) {
                $permit->items()->create($item);

                if (! empty($item['item_id'])) {
                    InventoryItem::whereKey($item['item_id'])->decrement('quantity', abs((float) $item['qty']));
                }
            }

            $this->autoPost->execute($permit->load('items'));

            $this->activityLogService->log(
                action: 'update',
                module: 'inventory',
                recordId: $permit->id,
                description: "تعديل إذن صرف رقم {$permit->permit_no}",
            );

            return $permit;
        });
    }

    private function returnItemsToStock(StockPermit $permit): void
    {
        foreach ($permit->items as $item) {
            if ($item->item_id) {
                InventoryItem::whereKey($item->item_id)->increment('quantity', abs((float) $item->qty));
            }
        }
    }

    /**
     * @param  array<int, array{item_id: string, qty: float}>  $items
     */
    private function guardAvailableQuantities(array $items): void
    {
        $requestedByItem = collect($items)
            ->filter(fn (array $item) => ! empty($item['item_id']))
            ->groupBy('item_id')
            ->map(fn ($lines) => $lines->sum(fn (array $line) => abs((float) $line['qty'])));

        foreach ($requestedByItem as $itemId => $requestedQty) {
            $inventoryItem = InventoryItem::whereKey($itemId)->lockForUpdate()->firstOrFail();

            if ((float) $inventoryItem->quantity < $requestedQty) {
                throw ValidationException::withMessages([
                    'items' => "الكمية المطلوبة من {$inventoryItem->name} ({$requestedQty}) أكبر من الرصيد المتاح ({$inventoryItem->quantity})",
                ]);
            }
        }
    }

    private function reverseJournalEntries(StockPermit $permit): void
    {
        JournalEntry::where('source', JournalSource::SUPPLIES_USED)
            ->where('idempotency_key', 'like', "stock_issue:{$permit->id}:%")
            ->whereNull('reversed_at')
            ->get()
            ->each(function (JournalEntry $entry) use ($permit) {
                $this->journalService->reverse(
                    $entry,
                    JournalSource::REVERSAL,
                    $permit->permit_no,
                    "عكس قيد صرف مخزون (تعديل): {$entry->description}",
                );
            });
    }
}
