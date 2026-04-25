<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Services\InventoryService;

class AddStockPermitAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(array $data, array $items): StockPermit
    {
        return DB::transaction(function () use ($data, $items) {
            $permit = StockPermit::create([
                ...$data,
                'type' => PermitType::IN,
                'permit_no' => $this->generatePermitNo(),
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $permit->items()->create($item);

                if (! empty($item['item_id'])) {
                    $this->inventoryService->adjustQuantity($item['item_id'], abs($item['qty']));
                }
            }

            $this->activityLogService->log(
                action: 'add',
                module: 'inventory',
                recordId: $permit->id,
                description: "إذن إضافة مخزون رقم {$permit->permit_no}",
            );

            return $permit->load('items');
        });
    }

    private function generatePermitNo(): string
    {
        $last = StockPermit::where('type', 'in')->latest()->value('permit_no');
        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return 'IN-'.date('Y').'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
