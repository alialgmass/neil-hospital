<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class HRAttendanceReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $employeeId = $request->input('employee_id');

        $employees = DB::table('employees')->orderBy('name')->get(['id', 'name', 'employee_no']);

        return Inertia::render('reports/HRAttendance', [
            'data' => $this->reportingService->hrAttendance($from, $to, $employeeId),
            'filters' => compact('from', 'to', 'employeeId'),
            'employees' => $employees,
        ]);
    }
}
