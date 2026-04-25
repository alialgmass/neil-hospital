<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Inventory\Enums\InvoiceStatus;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\Supplier;

class InventoryService
{
    /**
     * List inventory items with filters.
     */
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return InventoryItem::query()
            ->with('supplier:id,name')
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($iq) use ($v) {
                    $iq->where('name', 'like', "%{$v}%")
                        ->orWhere('code', 'like', "%{$v}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['low_stock'] ?? null, fn ($q) => $q->whereColumn('quantity', '<=', 'min_quantity')->where('min_quantity', '>', 0))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function categories(): Collection
    {
        return InventoryItem::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    public function lowStockCount(): int
    {
        return InventoryItem::query()
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->where('min_quantity', '>', 0)
            ->count();
    }

    public function totalValue(): float
    {
        return (float) InventoryItem::query()->sum(\DB::raw('quantity * unit_cost'));
    }

    public function openOrdersCount(): int
    {
        return PurchaseInvoice::whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->count();
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

    public function getStockTakeItems(): Collection
    {
        return InventoryItem::orderBy('category')->orderBy('name')->get([
            'id', 'name', 'code', 'category', 'unit', 'quantity', 'min_quantity',
        ]);
    }

    // --- Stock Permit Methods ---

    public function getStockPermits(int $perPage = 20): LengthAwarePaginator
    {
        return StockPermit::query()
            ->with(['items', 'creator'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getSelectableItems(): Collection
    {
        return InventoryItem::orderBy('name')->get(['id', 'name', 'unit', 'quantity', 'unit_cost']);
    }

    // --- Supplier Methods ---

    public function getSuppliers(?string $search = null, int $perPage = 30): LengthAwarePaginator
    {
        return Supplier::query()
            ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createSupplier(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function updateSupplier(string $id, array $data): Supplier
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($data);

        return $supplier;
    }
}
