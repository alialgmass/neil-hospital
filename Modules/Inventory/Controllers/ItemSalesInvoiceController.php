<?php

namespace Modules\Inventory\Controllers;

use App\Enums\Department;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Enums\PayMethod;
use Modules\Inventory\Actions\CreateItemSalesInvoiceAction;
use Modules\Inventory\Http\Requests\StoreItemSalesInvoiceRequest;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\ItemSalesInvoice;

class ItemSalesInvoiceController extends Controller
{
    public function __construct(
        private readonly CreateItemSalesInvoiceAction $createAction,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
        ]);

        $filters = [
            'from' => $validated['from'] ?? today()->toDateString(),
            'to' => $validated['to'] ?? today()->toDateString(),
            'search' => $validated['search'] ?? null,
        ];

        $query = ItemSalesInvoice::query()
            ->whereDate('invoice_date', '>=', $filters['from'])
            ->whereDate('invoice_date', '<=', $filters['to'])
            ->when($filters['search'], function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('invoice_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('file_no', 'like', "%{$search}%");
                });
            });

        $totals = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(discount), 0) as discount, COALESCE(SUM(total), 0) as total, COALESCE(SUM(cost_total), 0) as cost_total')
            ->first();

        return Inertia::render('inventory/ItemSales', [
            'filters' => $filters,
            'invoices' => $query
                ->with(['items', 'creator:id,name'])
                ->orderByDesc('invoice_date')
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'totals' => [
                'count' => (int) $totals->count,
                'subtotal' => (float) $totals->subtotal,
                'discount' => (float) $totals->discount,
                'total' => (float) $totals->total,
                'profit' => round((float) $totals->total - (float) $totals->cost_total, 2),
            ],
            'selectableItems' => InventoryItem::where('quantity', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'unit', 'quantity', 'sell_price']),
            'deptOptions' => Department::optionsForEnabledModules(),
            'payMethodOptions' => array_map(
                fn (PayMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                [PayMethod::Cash, PayMethod::Card, PayMethod::Transfer],
            ),
        ]);
    }

    public function store(StoreItemSalesInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->createAction->execute(
            $request->safe()->except('items'),
            $request->validated('items'),
        );

        return redirect()
            ->route('item-sales.show', $invoice->id)
            ->with('success', "تم إصدار فاتورة البيع رقم {$invoice->invoice_no}.");
    }

    public function show(string $id): Response
    {
        return Inertia::render('inventory/ItemSalesInvoicePrint', [
            'invoice' => ItemSalesInvoice::with(['items', 'creator:id,name'])->findOrFail($id),
        ]);
    }
}
