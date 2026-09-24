<?php

namespace Modules\Surgery\DTOs;

readonly class SuppliesUsedData
{
    /**
     * @param  array<int, array{inventory_item_id: string, bundle_id: ?string, name: string, qty: float, unit_cost: float, total: float, is_bundle: bool}>  $items
     */
    public function __construct(
        public string $surgeryId,
        public array $items,
    ) {}

    /**
     * Normalises submitted supply lines. Line totals are always computed
     * server-side (qty × unit_cost) and individual items repeated within the
     * same submission are merged into one line with the summed quantity.
     */
    public static function fromArray(array $data): self
    {
        $items = [];
        $indexByItem = [];

        foreach ($data['items'] ?? [] as $item) {
            $qty = (float) ($item['qty'] ?? 1);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $isBundle = (bool) ($item['is_bundle'] ?? false);
            $itemId = (string) ($item['inventory_item_id'] ?? '');

            if (! $isBundle && $itemId !== '' && isset($indexByItem[$itemId])) {
                $existing = &$items[$indexByItem[$itemId]];
                $existing['qty'] += $qty;
                $existing['total'] = round($existing['qty'] * $existing['unit_cost'], 2);
                unset($existing);

                continue;
            }

            $items[] = [
                'inventory_item_id' => $itemId,
                'bundle_id' => $item['bundle_id'] ?? null,
                'name' => $item['name'] ?? '',
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total' => round($qty * $unitCost, 2),
                'is_bundle' => $isBundle,
            ];

            if (! $isBundle && $itemId !== '') {
                $indexByItem[$itemId] = array_key_last($items);
            }
        }

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
