<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\DashboardService;

class ReportController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function daily(): Response
    {
        $date = request('date', today()->toDateString());
        $from = $date;
        $to = $date;

        return Inertia::render('reports/Daily', [
            'date' => $date,
            'revenueByDept' => $this->dashboardService->revenueByDept($from, $to),
            'revenueByDoc' => $this->dashboardService->revenueByDoctor($from, $to),
            'treasury' => $this->dashboardService->treasuryBalance(),
        ]);
    }

    public function income(): Response
    {
        $from = request('from', today()->startOfMonth()->toDateString());
        $to = request('to', today()->toDateString());

        return Inertia::render('reports/Income', [
            'from' => $from,
            'to' => $to,
            'stats' => $this->dashboardService->getIncomeStats($from, $to),
            'revenueByDept' => $this->dashboardService->revenueByDept($from, $to),
            'revenueByDoc' => $this->dashboardService->revenueByDoctor($from, $to),
        ]);
    }
}
