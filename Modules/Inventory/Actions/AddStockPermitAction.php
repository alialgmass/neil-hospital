<?php

namespace Modules\Inventory\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Services\InventoryService;

class AddStockPermitAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(array $data, array $items): StockPermit
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->attempt($data, $items);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::MAX_ATTEMPTS || ! str_contains($e->getMessage(), 'permit_no')) {
                    throw ValidationException::withMessages([
                        'items' => 'تعذر إصدار رقم الإذن بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
                    ]);
                }
            }
        }

        throw ValidationException::withMessages([
            'items' => 'تعذر إصدار رقم الإذن بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
        ]);
    }

    private function attempt(array $data, array $items): StockPermit
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
        $prefix = 'IN-'.date('Y').'-';

        $last = StockPermit::where('type', 'in')
            ->where('permit_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('permit_no')
            ->value('permit_no');

        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
