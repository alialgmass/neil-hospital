<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Actions\ReceivePurchaseInvoiceAction;
use Modules\Inventory\Enums\InvoiceStatus;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Services\PurchaseInvoiceService;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $purchaseService,
        private readonly ReceivePurchaseInvoiceAction $receiveAction,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['from', 'to', 'supplier_id', 'status']);

        $stats = [
            'invoice_count' => PurchaseInvoice::count(),
            'total_amount' => (float) PurchaseInvoice::sum('total'),
            'unpaid_count' => PurchaseInvoice::whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->count(),
            'total_due' => (float) PurchaseInvoice::whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->sum('remaining'),
        ];

        return Inertia::render('purchases/Index', [
            'invoices' => $this->purchaseService->list($filters, 30),
            'suppliers' => $this->purchaseService->getActiveSuppliers(),
            'filters' => $filters,
            'stats' => $stats,
            'next_invoice_no' => $this->nextInvoiceNo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_no' => ['nullable', 'string', 'max:50', 'unique:purchase_invoices,invoice_no'],
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

        // Auto-generate invoice number if not provided
        if (empty($data['invoice_no'])) {
            $data['invoice_no'] = $this->nextInvoiceNo();
        }

        $items = $data['items'];
        $this->receiveAction->execute($data, $items);

        return back()->with('success', 'تم تسجيل فاتورة المشتريات بنجاح.');
    }

    /**
     * Live item lookup for the invoice-line autocomplete: name/code, purchase
     * price, sell price, and expiry date, sourced from inventory.
     */
    public function searchItems(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $items = InventoryItem::search($term)
            ->select('id', 'name', 'code', 'unit_cost', 'sell_price', 'expiry_date')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'invoice_no' => ['nullable', 'string', 'max:50', 'unique:purchase_invoices,invoice_no,'.$id],
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

        $invoice = $this->purchaseService->update($id, $data, $data['items']);

        $this->activityLog->log('purchase_invoice_edited', 'inventory', $invoice->id, "تعديل فاتورة مشتريات: {$invoice->invoice_no}");

        return back()->with('success', 'تم تحديث فاتورة المشتريات بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $invoiceNo = PurchaseInvoice::whereKey($id)->value('invoice_no');

        $this->purchaseService->delete($id);

        $this->activityLog->log('purchase_invoice_deleted', 'inventory', $id, "حذف فاتورة مشتريات: {$invoiceNo}");

        return back()->with('success', 'تم حذف فاتورة المشتريات بنجاح.');
    }

    private function nextInvoiceNo(): string
    {
        $last = PurchaseInvoice::max('invoice_no');

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            return 'PI-'.str_pad((int) $m[1] + 1, 4, '0', STR_PAD_LEFT);
        }

        return 'PI-0001';
    }
}
