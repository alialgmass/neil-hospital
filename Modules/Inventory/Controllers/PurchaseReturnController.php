<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Services\PurchaseReturnService;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render('inventory/PurchaseReturns', [
            'returns' => $this->service->list(20),
            'invoices' => $this->service->getEligibleInvoices(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => 'required|string|exists:purchase_invoices,id',
            'reason' => 'nullable|string|max:300',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|string|exists:inventory,id',
            'items.*.item_name' => 'required|string|max:200',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $this->service->processReturn($data);

        return back()->with('success', 'تم تسجيل المرتجع بنجاح وتعديل المخزون.');
    }
}
