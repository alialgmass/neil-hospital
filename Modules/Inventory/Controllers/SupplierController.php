<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\RecordSupplierPaymentAction;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\InventoryService;

class SupplierController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly RecordSupplierPaymentAction $recordSupplierPayment,
    ) {}

    public function index(): Response
    {
        $search = request('search');

        $stats = [
            'total_suppliers' => Supplier::count(),
            'total_purchases' => (float) PurchaseInvoice::sum('total'),
            'total_due' => (float) Supplier::sum('balance'),
        ];

        return Inertia::render('suppliers/Index', [
            'suppliers' => $this->inventoryService->getSuppliers($search),
            'filters' => ['search' => $search],
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'terms' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->inventoryService->createSupplier($data);

        return back()->with('success', 'تم إضافة المورد بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'terms' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $this->inventoryService->updateSupplier($id, $data);

        return back()->with('success', 'تم تعديل المورد بنجاح.');
    }

    public function pay(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,transfer'],
            'reference' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['supplier_id'] = $id;
        $data['paid_at'] ??= now()->toDateString();

        $this->recordSupplierPayment->execute($data);

        return back()->with('success', 'تم تسجيل سداد المورد بنجاح.');
    }
}
