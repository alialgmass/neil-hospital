<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Models\Account;
use Modules\Inventory\Imports\ServicesImport;
use Modules\Inventory\Services\MedicalServiceService;

class ServiceController extends Controller
{
    public function __construct(
        private readonly MedicalServiceService $service
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'status']);

        $revenueAccounts = Account::where('group', 'revenues')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('inventory/Services', [
            'services' => $this->service->list($filters, 30),
            'filters' => $filters,
            'revenueAccounts' => $revenueAccounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'dept' => 'required|in:clinic,labs,surgery,lasik,laser',
            'price' => 'nullable|numeric|min:0',
            'ins_price' => 'nullable|numeric|min:0',
            'center_type' => 'required|in:pct,fixed',
            'center_val' => 'nullable|numeric|min:0',
            'duration_mins' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
            'revenue_account_id' => 'nullable|ulid|exists:accounts,id',
        ]);

        $this->service->create($data);

        return back()->with('success', 'تم إضافة الخدمة بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'dept' => 'required|in:clinic,labs,surgery,lasik,laser',
            'price' => 'nullable|numeric|min:0',
            'ins_price' => 'nullable|numeric|min:0',
            'center_type' => 'required|in:pct,fixed',
            'center_val' => 'nullable|numeric|min:0',
            'duration_mins' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
            'revenue_account_id' => 'nullable|ulid|exists:accounts,id',
        ]);

        $this->service->update($id, $data);

        return back()->with('success', 'تم تعديل الخدمة بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->service->delete($id);

        return back()->with('success', 'تم حذف الخدمة.');
    }

    public function toggleStatus(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $this->service->toggleStatus($id, $data['status']);

        return back();
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new ServicesImport;
        Excel::import($import, $request->file('file'));

        Inertia::flash('importResult', [
            'created' => $import->created,
            'updated' => $import->updated,
            'skipped' => $import->skipped,
        ]);

        return back();
    }
}
