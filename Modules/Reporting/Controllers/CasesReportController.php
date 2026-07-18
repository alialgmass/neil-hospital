<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class CasesReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());
        $dept = $request->input('dept');

        return Inertia::render('reports/CasesReport', [
            'data' => $this->reportingService->cases($from, $to, $dept),
            'filters' => compact('from', 'to', 'dept'),
        ]);
    }
}
