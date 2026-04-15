<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Inventory\Models\InventoryItem;

class InventoryService
{
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return InventoryItem::query()
            ->with('supplier')
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%")->orWhere('code', 'like', "%{$v}%"))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['low_stock'] ?? null, fn ($q) => $q->whereRaw('quantity <= min_quantity AND min_quantity > 0'))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function lowStockCount(): int
    {
        return InventoryItem::whereRaw('quantity <= min_quantity AND min_quantity > 0')->count();
    }

    public function create(array $data): InventoryItem
    {
        return InventoryItem::create($data);
    }

    public function update(string $id, array $data): InventoryItem
    {
        $item = InventoryItem::findOrFail($id);
        $item->update($data);

        return $item;
    }

    public function adjustQuantity(string $id, float $delta): InventoryItem
    {
        $item = InventoryItem::findOrFail($id);
        $item->increment('quantity', $delta);

        return $item->refresh();
    }

    public function categories(): SupportCollection
    {
        return InventoryItem::distinct('category')
            ->orderBy('category')
            ->pluck('category');
    }
}
