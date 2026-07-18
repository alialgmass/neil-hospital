<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ExcelExportService;
use Modules\Reporting\Services\ReportingService;

class ProfitLossController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly ExcelExportService $excelExportService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());

        return Inertia::render('reports/ProfitLoss', [
            'data' => $this->reportingService->profitLoss($from, $to),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function export(Request $request)
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());

        return $this->excelExportService->export('profit-loss', $this->reportingService->profitLoss($from, $to));
    }
}
