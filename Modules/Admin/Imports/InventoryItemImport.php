<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Inventory\Models\InventoryItem;

class InventoryItemImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['الصنف'] ?? $row['name'] ?? ''));

            if (empty($name)) {
                $this->skipped++;

                continue;
            }

            $data = [
                'code' => $row['الكود'] ?? $row['code'] ?? null,
                'category' => $row['الفئة'] ?? $row['category'] ?? null,
                'unit' => $row['الوحدة'] ?? $row['unit'] ?? null,
                'min_quantity' => (float) ($row['الحد الأدنى'] ?? $row['min_quantity'] ?? 0),
                'unit_cost' => (float) ($row['سعر الشراء'] ?? $row['unit_cost'] ?? 0),
                'sell_price' => (float) ($row['سعر البيع'] ?? $row['sell_price'] ?? 0),
            ];

            $existing = InventoryItem::where('name', $name)->first();

            if ($existing) {
                $existing->update(collect($data)->filter()->toArray());
                $this->updated++;
            } else {
                InventoryItem::create(array_merge(['name' => $name, 'quantity' => 0], collect($data)->filter()->toArray()));
                $this->created++;
            }
        }
    }
}
