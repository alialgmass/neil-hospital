<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Services\InventoryService;

class SupplierController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function index(): Response
    {
        $search = request('search');

        return Inertia::render('suppliers/Index', [
            'suppliers' => $this->inventoryService->getSuppliers($search),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'tax_no' => ['nullable', 'string', 'max:50'],
        ]);

        $this->inventoryService->createSupplier($data);

        return back()->with('success', 'تم إضافة المورد بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        $this->inventoryService->updateSupplier($id, $data);

        return back()->with('success', 'تم تعديل المورد بنجاح.');
    }
}
