<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reporting\Services\ReportingService;

/**
 * Revenue/expense/net-profit per cost center for a date range (a month, a
 * year, or an arbitrary range). Returned as JSON — no dedicated Inertia
 * page exists yet; a dashboard screen for this is separate follow-up UI
 * work (out of scope for the accounting-guide-v3 spec).
 */
class CostCenterProfitabilityController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $from = $request->input('from', today()->subDays(30)->toDateString());
        $to = $request->input('to', today()->toDateString());

        return response()->json(
            $this->reportingService->costCenterProfitability($from, $to)
        );
    }
}
