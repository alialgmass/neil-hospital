<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\Payroll;
use Modules\HR\Services\HRService;

class PayrollController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);
        $filters = ['month' => $month, 'year' => $year, 'status' => request('status')];

        $stats = [
            'total_employees' => Payroll::where('month', $month)->where('year', $year)->count(),
            'total_net' => (float) Payroll::where('month', $month)->where('year', $year)->sum('net_salary'),
            'paid_count' => Payroll::where('month', $month)->where('year', $year)->where('status', 'paid')->count(),
            'draft_count' => Payroll::where('month', $month)->where('year', $year)->where('status', 'draft')->count(),
        ];

        return Inertia::render('hr/Payroll', [
            'payrolls' => $this->hr->listPayroll($filters, 50),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $count = $this->hr->generatePayroll((int) $data['month'], (int) $data['year']);

        return back()->with('success', "تم إنشاء {$count} سجل راتب جديد.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->hr->updatePayroll($id, $data);

        return back()->with('success', 'تم تعديل الراتب بنجاح.');
    }

    public function approve(string $id): RedirectResponse
    {
        $this->hr->approvePayroll($id);

        return back()->with('success', 'تمت الموافقة على الراتب.');
    }

    public function markPaid(string $id): RedirectResponse
    {
        $this->hr->markPayrollPaid($id);

        return back()->with('success', 'تم تسجيل صرف الراتب.');
    }

    public function recalculate(string $id): RedirectResponse
    {
        $this->hr->recalculatePayroll($id);

        return back()->with('success', 'تم إعادة حساب الراتب من سجلات الحضور.');
    }
}
