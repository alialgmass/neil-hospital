<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Enums\PayrollStatus;
use Modules\HR\Models\Payroll;
use Modules\HR\Requests\GeneratePayrollRequest;
use Modules\HR\Requests\UpdatePayrollRequest;
use Modules\HR\Services\HRService;

class PayrollController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $month = (int) request('month', now()->month);
        $year = (int) request('year', now()->year);
        $filters = ['month' => $month, 'year' => $year, 'status' => request('status')];

        $base = Payroll::where('month', $month)->where('year', $year);

        $stats = [
            'total_employees' => (clone $base)->count(),
            'total_net' => (float) (clone $base)->sum('net_salary'),
            'paid_count' => (clone $base)->where('status', PayrollStatus::Paid)->count(),
            'draft_count' => (clone $base)->where('status', PayrollStatus::Draft)->count(),
        ];

        return Inertia::render('hr/Payroll', [
            'payrolls' => $this->hr->listPayroll($filters, 50),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function generate(GeneratePayrollRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $count = $this->hr->generatePayroll((int) $data['month'], (int) $data['year']);

        return back()->with('success', "تم إنشاء {$count} سجل راتب جديد.");
    }

    public function update(UpdatePayrollRequest $request, string $id): RedirectResponse
    {
        $this->hr->updatePayroll($id, $request->validated());

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
