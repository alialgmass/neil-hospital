<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\ItemUnit;
use Modules\Inventory\Services\InventoryService;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'category', 'low_stock']);

        return Inertia::render('inventory/Index', [
            'items' => $this->inventoryService->list($filters, 30),
            'categories' => collect(ItemCategory::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'units' => collect(ItemUnit::cases())->map(fn ($u) => ['value' => $u->value, 'label' => $u->label()]),
            'lowStockCount' => $this->inventoryService->lowStockCount(),
            'totalValue' => $this->inventoryService->totalValue(),
            'openOrdersCount' => $this->inventoryService->openOrdersCount(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:40', 'unique:inventory,code'],
            'category' => ['nullable', Rule::enum(ItemCategory::class)],
            'unit' => ['nullable', Rule::enum(ItemUnit::class)],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'expiry_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:80'],
        ]);

        $item = $this->inventoryService->create($data);

        $this->activityLog->log('item_created', 'inventory', $item->id, "إضافة صنف: {$item->name}");

        return back()->with('success', 'تم إضافة الصنف بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:40', "unique:inventory,code,{$id}"],
            'category' => ['nullable', Rule::enum(ItemCategory::class)],
            'unit' => ['nullable', Rule::enum(ItemUnit::class)],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'expiry_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:80'],
        ]);

        $item = $this->inventoryService->update($id, $data);

        $this->activityLog->log('item_updated', 'inventory', $id, "تعديل صنف: {$item->name}");

        return back()->with('success', 'تم تعديل الصنف بنجاح.');
    }
}
