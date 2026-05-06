<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Inventory\Enums\InvoiceStatus;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\StockPermitItem;
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

    public function adjustQuantity(string $id, float $delta): void
    {
        InventoryItem::where('id', $id)->increment('quantity', $delta);
    }

    public function getIssuedPermits(string $date, int $perPage = 20): LengthAwarePaginator
    {
        return StockPermit::query()
            ->where('type', 'out')
            ->whereDate('created_at', $date)
            ->with(['items', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getDailyConsumption(string $date): Collection
    {
        return StockPermitItem::query()
            ->join('stock_permits', 'stock_permit_items.permit_id', '=', 'stock_permits.id')
            ->where('stock_permits.type', 'out')
            ->whereDate('stock_permits.created_at', $date)
            ->selectRaw('stock_permit_items.item_id, stock_permit_items.item_name, SUM(stock_permit_items.qty) as total_qty, SUM(stock_permit_items.qty * stock_permit_items.unit_cost) as total_value')
            ->groupBy('stock_permit_items.item_id', 'stock_permit_items.item_name')
            ->orderByDesc('total_value')
            ->get();
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
