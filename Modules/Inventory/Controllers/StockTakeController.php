<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\StockTakeAdjustmentAction;
use Modules\Inventory\Services\InventoryService;

class StockTakeController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockTakeAdjustmentAction $adjustmentAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('inventory/StockTake', [
            'items' => $this->inventoryService->getStockTakeItems(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'counts' => 'required|array|min:1',
            'counts.*.item_id' => 'required|string|exists:inventory,id',
            'counts.*.physical_qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $this->adjustmentAction->execute($data['counts'], $data['notes'] ?? '');

        return back()->with('success', 'تمت تسوية الجرد بنجاح.');
    }
}
