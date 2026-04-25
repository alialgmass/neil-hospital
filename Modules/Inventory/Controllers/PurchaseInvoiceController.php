<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Services\PurchaseInvoiceService;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $purchaseService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['from', 'to']);

        return Inertia::render('purchases/Index', [
            'invoices' => $this->purchaseService->list($filters, 30),
            'suppliers' => $this->purchaseService->getActiveSuppliers(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_no' => ['required', 'string', 'max:50', 'unique:purchase_invoices,invoice_no'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'invoice_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.item_id' => ['nullable', 'exists:inventory,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $items = $data['items'];
        $invoice = $this->purchaseService->create($data, $items);

        $this->activityLog->log('purchase_invoice', 'inventory', $invoice->id, "فاتورة مشتريات: {$invoice->invoice_no}");

        return back()->with('success', 'تم تسجيل فاتورة المشتريات بنجاح.');
    }
}
