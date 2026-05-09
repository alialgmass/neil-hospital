<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\ReportingService;

class PurchasePriceReportController extends Controller
{
    public function __construct(private readonly ReportingService $reportingService) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());

        return Inertia::render('reports/PurchasePrices', [
            'data' => $this->reportingService->purchasePrices($from, $to),
            'filters' => compact('from', 'to'),
        ]);
    }
}
