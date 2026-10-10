<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\EmployeeDeduction;
use Modules\HR\Requests\StoreEmployeeDeductionRequest;
use Modules\HR\Services\HRService;

class EmployeeDeductionController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['employee_id', 'from', 'to']);

        return Inertia::render('hr/Deductions', [
            'deductions' => $this->hr->listDeductions($filters, 25),
            'employees' => $this->hr->getActiveEmployees(),
            'workingDays' => $this->hr->workingDaysPerMonth(),
            'filters' => $filters,
            'totals' => [
                'count' => EmployeeDeduction::query()
                    ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
                    ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('deduction_date', '>=', $v))
                    ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('deduction_date', '<=', $v))
                    ->count(),
                'amount' => $this->hr->totalDeductions($filters),
            ],
        ]);
    }

    public function store(StoreEmployeeDeductionRequest $request): RedirectResponse
    {
        $this->hr->createDeduction($request->validated());

        return back()->with('success', 'تم تسجيل الخصم وتطبيقه على راتب الشهر.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->hr->deleteDeduction($id);

        return back()->with('success', 'تم حذف الخصم وإعادته لراتب الشهر.');
    }
}
