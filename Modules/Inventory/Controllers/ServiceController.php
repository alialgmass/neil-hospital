<?php

namespace Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Imports\ServicesImport;
use Modules\Inventory\Models\Service;

class ServiceController extends Controller
{
    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'status']);

        $services = Service::query()
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($filters['dept'] ?? null, fn ($q, $v) => $q->where('dept', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('dept')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inventory/Services', [
            'services' => $services,
            'filters' => $filters,
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
        ]);

        Service::create($this->appendShares($data));

        return back()->with('success', 'تم إضافة الخدمة بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $service = Service::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'dept' => 'required|in:clinic,labs,surgery,lasik,laser',
            'price' => 'nullable|numeric|min:0',
            'ins_price' => 'nullable|numeric|min:0',
            'center_type' => 'required|in:pct,fixed',
            'center_val' => 'nullable|numeric|min:0',
            'duration_mins' => 'nullable|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ]);

        $service->update($this->appendShares($data));

        return back()->with('success', 'تم تعديل الخدمة بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        Service::findOrFail($id)->delete();

        return back()->with('success', 'تم حذف الخدمة.');
    }

    /** @param array<string, mixed> $data */
    private function appendShares(array $data): array
    {
        $price = (float) ($data['price'] ?? 0);
        $val = (float) ($data['center_val'] ?? 0);

        $centerShare = $data['center_type'] === 'pct'
            ? round($price * $val / 100, 2)
            : $val;

        $data['center_share'] = $centerShare;
        $data['dr_share'] = round($price - $centerShare, 2);

        return $data;
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new ServicesImport;
        Excel::import($import, $request->file('file'));

        return back()->with('success', "تم الاستيراد: {$import->created} جديدة، {$import->updated} محدّثة، {$import->skipped} متجاهلة.");
    }
}
