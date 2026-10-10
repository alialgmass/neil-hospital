<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Actions\AutoPostStockTakeAdjustmentAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;

class StockTakeAdjustmentAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostStockTakeAdjustmentAction $autoPost,
    ) {}

    /**
     * @param  array<array{item_id: string, physical_qty: float}>  $counts
     */
    public function execute(array $counts, string $notes = ''): StockPermit
    {
        return DB::transaction(function () use ($counts, $notes) {
            $permitNo = 'ADJ-'.date('Y').'-'.str_pad(
                StockPermit::whereIn('type', ['in', 'out'])->whereRaw("permit_no LIKE 'ADJ-%'")->count() + 1,
                5,
                '0',
                STR_PAD_LEFT
            );

            // Determine dominant direction (in or out) based on net variance
            $netVariance = 0;
            $adjustItems = [];
            $variances = [];

            foreach ($counts as $count) {
                $item = InventoryItem::findOrFail($count['item_id']);
                $variance = $count['physical_qty'] - $item->quantity;

                $adjustItems[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'qty' => abs($variance),
                    'unit_cost' => $item->unit_cost,
                ];

                $variances[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'variance' => (float) $variance,
                    'unit_cost' => (float) $item->unit_cost,
                    'category' => $item->category,
                ];

                $netVariance += $variance;

                // Directly set quantity to physical count
                $item->update(['quantity' => max(0, $count['physical_qty'])]);
            }

            $permit = StockPermit::create([
                'permit_no' => $permitNo,
                'type' => $netVariance >= 0 ? 'in' : 'out',
                'reason' => 'تسوية جرد',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($adjustItems as $item) {
                $permit->items()->create($item);
            }

            $this->autoPost->execute($permit, $variances);

            $this->activityLogService->log(
                action: 'adjust',
                module: 'inventory',
                recordId: $permit->id,
                description: "تسوية جرد رقم {$permitNo}",
            );

            return $permit->load('items');
        });
    }
}
