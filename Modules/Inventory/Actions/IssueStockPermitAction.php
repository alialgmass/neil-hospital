<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Services\InventoryService;

class IssueStockPermitAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(array $data, array $items): StockPermit
    {
        return DB::transaction(function () use ($data, $items) {
            // Validate sufficient quantity for each item
            foreach ($items as $item) {
                if (empty($item['item_id'])) {
                    continue;
                }

                $inventoryItem = InventoryItem::findOrFail($item['item_id']);
                if ($inventoryItem->quantity < $item['qty']) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المطلوبة من {$inventoryItem->name} ({$item['qty']}) أكبر من الرصيد المتاح ({$inventoryItem->quantity})",
                    ]);
                }
            }

            $permit = StockPermit::create([
                ...$data,
                'type' => PermitType::OUT,
                'permit_no' => $this->generatePermitNo(),
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $permit->items()->create($item);

                if (! empty($item['item_id'])) {
                    $this->inventoryService->adjustQuantity($item['item_id'], -abs($item['qty']));
                }
            }

            $this->activityLogService->log(
                action: 'issue',
                module: 'inventory',
                recordId: $permit->id,
                description: "إذن صرف رقم {$permit->permit_no}",
            );

            return $permit->load('items');
        });
    }

    private function generatePermitNo(): string
    {
        $last = StockPermit::where('type', 'out')->latest()->value('permit_no');
        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return 'OUT-'.date('Y').'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
