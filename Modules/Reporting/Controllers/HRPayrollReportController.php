<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class HRPayrollReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $month = (int) $request->input('month', today()->month);
        $year = (int) $request->input('year', today()->year);

        return Inertia::render('reports/HRPayroll', [
            'data' => $this->reportingService->hrPayroll($month, $year),
            'filters' => compact('month', 'year'),
        ]);
    }
}
