<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\Employee;
use Modules\HR\Services\HRService;

class EmployeeController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['search', 'dept', 'status']);

        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'inactive' => Employee::where('status', 'inactive')->count(),
        ];

        return Inertia::render('hr/Employees', [
            'employees' => $this->hr->listEmployees($filters, 30),
            'depts' => $this->hr->getDepts(),
            'filters' => $filters,
            'stats' => $stats,
            'next_employee_no' => $this->hr->nextEmployeeNo(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_no' => ['nullable', 'string', 'max:20', 'unique:employees,employee_no'],
            'name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'dept' => ['required', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['employee_no'])) {
            $data['employee_no'] = $this->hr->nextEmployeeNo();
        }

        $this->hr->createEmployee($data);

        return response()->json([]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'dept' => ['required', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'contract_type' => ['required', 'string'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->hr->updateEmployee($id, $data);

        return back()->with('success', 'تم تعديل بيانات الموظف بنجاح.');
    }
}
