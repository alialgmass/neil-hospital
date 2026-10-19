<?php

namespace Modules\Inventory\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return InventoryItem::query()
            ->with('supplier')
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(string $id): InventoryItem
    {
        return InventoryItem::findOrFail($id);
    }

    public function create(array $data): InventoryItem
    {
        return InventoryItem::create($data);
    }

    public function update(string $id, array $data): InventoryItem
    {
        $item = $this->findById($id);
        $item->update($data);

        return $item->fresh();
    }

    public function lowStockCount(): int
    {
        return InventoryItem::active()->whereRaw('quantity <= min_quantity AND min_quantity > 0')->count();
    }

    public function adjustQuantity(string $id, float $delta): void
    {
        InventoryItem::where('id', $id)->increment('quantity', $delta);
    }
}
