<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class HRLeavesReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $employeeId = $request->input('employee_id');
        $type = $request->input('type');

        $employees = DB::table('employees')->orderBy('name')->get(['id', 'name', 'employee_no']);

        return Inertia::render('reports/HRLeaves', [
            'data' => $this->reportingService->hrLeaves($from, $to, $employeeId, $type),
            'filters' => compact('from', 'to', 'employeeId', 'type'),
            'employees' => $employees,
        ]);
    }
}
