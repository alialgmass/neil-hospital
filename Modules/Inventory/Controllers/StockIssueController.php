<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\IssueStockPermitAction;
use Modules\Inventory\Actions\UpdateStockPermitAction;
use Modules\Inventory\Http\Requests\StoreStockPermitRequest;
use Modules\Inventory\Services\InventoryService;

class StockIssueController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly IssueStockPermitAction $issueAction,
        private readonly UpdateStockPermitAction $updateAction,
    ) {}

    public function index(Request $request): Response
    {
        $date = $request->input('date', today()->toDateString());

        $permits = $this->inventoryService->getIssuedPermits($date);
        $consumption = $this->inventoryService->getDailyConsumption($date);

        return Inertia::render('inventory/StockIssue', [
            'date' => $date,
            'permits' => $permits,
            'consumption' => $consumption,
            'voucherCount' => $permits->total(),
            'totalItems' => (float) $consumption->sum('total_qty'),
            'totalValue' => (float) $consumption->sum('total_value'),
            'selectableItems' => $this->inventoryService->getSelectableItems(),
        ]);
    }

    public function store(StoreStockPermitRequest $request): RedirectResponse
    {
        $this->issueAction->execute(
            $request->only(['department', 'reason', 'notes']),
            $request->input('items', []),
        );

        return back()->with('success', 'تم إصدار إذن الصرف بنجاح.');
    }

    public function update(StoreStockPermitRequest $request, string $id): RedirectResponse
    {
        $this->updateAction->execute(
            $id,
            $request->only(['department', 'reason', 'notes']),
            $request->input('items', []),
        );

        return back()->with('success', 'تم تعديل إذن الصرف بنجاح.');
    }
}
