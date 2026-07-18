<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\AddStockPermitAction;
use Modules\Inventory\Actions\IssueStockPermitAction;
use Modules\Inventory\Http\Requests\StoreStockPermitRequest;
use Modules\Inventory\Services\InventoryService;

class StockPermitController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly IssueStockPermitAction $issueAction,
        private readonly AddStockPermitAction $addAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('inventory/StockPermit', [
            'permits' => $this->inventoryService->getStockPermits(20),
            'items' => $this->inventoryService->getSelectableItems(),
        ]);
    }

    public function issue(StoreStockPermitRequest $request): RedirectResponse
    {
        $this->issueAction->execute(
            $request->only(['department', 'reason', 'notes']),
            $request->input('items', []),
        );

        return back()->with('success', 'تم إصدار إذن الصرف بنجاح.');
    }

    public function add(StoreStockPermitRequest $request): RedirectResponse
    {
        $this->addAction->execute(
            $request->only(['department', 'reason', 'notes']),
            $request->input('items', []),
        );

        return back()->with('success', 'تم إضافة إذن الإضافة بنجاح.');
    }
}
