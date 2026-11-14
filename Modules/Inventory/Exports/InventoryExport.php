<?php

namespace Modules\Inventory\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $items) {}

    public function collection(): Collection
    {
        return $this->items;
    }

    public function headings(): array
    {
        return ['اسم الصنف', 'الكود', 'التصنيف', 'الوحدة', 'الكمية', 'الحد الأدنى', 'سعر الشراء', 'سعر البيع', 'المورد', 'تاريخ الانتهاء', 'الموقع'];
    }

    public function map($item): array
    {
        return [
            $item->name,
            $item->code ?? '—',
            $item->category_label ?? '—',
            $item->unit_label ?? '—',
            (float) $item->quantity,
            (float) $item->min_quantity,
            (float) $item->unit_cost,
            (float) $item->sell_price,
            $item->supplier?->name ?? '—',
            $item->expiry_date?->toDateString() ?? '—',
            $item->location ?? '—',
        ];
    }
}
