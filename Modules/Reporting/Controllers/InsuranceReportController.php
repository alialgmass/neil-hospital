<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class InsuranceReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $companyId = $request->input('company_id');

        return Inertia::render('reports/InsuranceClaims', [
            'data' => $this->reportingService->insuranceClaims($from, $to, $companyId),
            'filters' => compact('from', 'to', 'companyId'),
        ]);
    }
}
