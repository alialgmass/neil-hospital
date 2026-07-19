<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Requests\StoreEmployeeRequest;
use Modules\HR\Requests\UpdateEmployeeRequest;
use Modules\HR\Services\HRService;

class EmployeeController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'status']);

        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('status', EmployeeStatus::Active)->count(),
            'on_leave' => Employee::where('status', EmployeeStatus::OnLeave)->count(),
            'inactive' => Employee::where('status', EmployeeStatus::Inactive)->count(),
        ];

        return Inertia::render('hr/Employees', [
            'employees' => $this->hr->listEmployees($filters, 30),
            'depts' => $this->hr->getDepts(),
            'dept_options' => $this->hr->getDepartments(),
            'filters' => $filters,
            'stats' => $stats,
            'next_employee_no' => $this->hr->nextEmployeeNo(),
            'roles' => $this->hr->getUserRoles(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['employee_no'])) {
            $data['employee_no'] = $this->hr->nextEmployeeNo();
        }

        $this->hr->createEmployee($data);

        return back()->with('success', 'تم إضافة الموظف بنجاح.');
    }

    public function update(UpdateEmployeeRequest $request, string $id): RedirectResponse
    {
        $this->hr->updateEmployee($id, $request->validated());

        return back()->with('success', 'تم تعديل بيانات الموظف بنجاح.');
    }
}
