<?php

namespace Modules\Surgery\DTOs;

readonly class SuppliesUsedData
{
    /**
     * @param  array<int, array{inventory_item_id: string, name: string, qty: float, unit_cost: float, total: float}>  $items
     */
    public function __construct(
        public string $surgeryId,
        public array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(function ($item) {
            $qty = (float) ($item['qty'] ?? 1);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            return [
                'inventory_item_id' => $item['inventory_item_id'] ?? '',
                'name' => $item['name'] ?? '',
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total' => $qty * $unitCost,
            ];
        }, $data['items'] ?? []);

        return new self(
            surgeryId: $data['surgery_id'],
            items: $items,
        );
    }

    public function total(): float
    {
        return array_sum(array_map(fn ($item) => (float) ($item['total'] ?? 0), $this->items));
    }
}
