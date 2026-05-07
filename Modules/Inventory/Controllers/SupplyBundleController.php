<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Http\Requests\StoreSupplyBundleRequest;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\SupplyBundle;

class SupplyBundleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('inventory/Bundles', [
            'bundles' => SupplyBundle::with('items.inventoryItem')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'inventoryItems' => InventoryItem::orderBy('name')
                ->get(['id', 'name', 'code', 'unit', 'unit_cost', 'quantity']),
        ]);
    }

    public function store(StoreSupplyBundleRequest $request): RedirectResponse
    {
        $bundle = SupplyBundle::create($request->safe()->except('items'));

        foreach ($request->input('items', []) as $item) {
            $bundle->items()->create([
                'inventory_item_id' => $item['inventory_item_id'] ?: null,
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'] ?? 0,
            ]);
        }

        return redirect()->route('supply-bundles.index')->with('success', 'تم إنشاء البند بنجاح.');
    }

    public function update(string $id, StoreSupplyBundleRequest $request): RedirectResponse
    {
        $bundle = SupplyBundle::findOrFail($id);
        $bundle->update($request->safe()->except('items'));

        $bundle->items()->delete();

        foreach ($request->input('items', []) as $item) {
            $bundle->items()->create([
                'inventory_item_id' => $item['inventory_item_id'] ?: null,
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'] ?? 0,
            ]);
        }

        return redirect()->route('supply-bundles.index')->with('success', 'تم تحديث البند بنجاح.');
    }
}
