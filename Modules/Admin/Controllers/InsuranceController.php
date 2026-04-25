<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Insurance\Services\InsuranceService;

class InsuranceController extends Controller
{
    public function __construct(
        private readonly InsuranceService $insuranceService
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Insurance', [
            'companies' => $this->insuranceService->list(request('search'), 30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'coverage_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'disc_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'contract_no' => ['nullable', 'string', 'max:50'],
        ]);

        $this->insuranceService->create($data);

        return back()->with('success', 'تم إضافة شركة التأمين بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'coverage_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'disc_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $this->insuranceService->update($id, $data);

        return back()->with('success', 'تم تعديل بيانات شركة التأمين بنجاح.');
    }
}
