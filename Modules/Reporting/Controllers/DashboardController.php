<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): Response
    {
        $from = request('from');
        $to = request('to');

        return Inertia::render('Dashboard', [
            'todayStats' => $this->dashboardService->todayStats(),
            'revenueByDept' => $this->dashboardService->revenueByDept($from, $to),
            'revenueByDoc' => $this->dashboardService->revenueByDoctor($from, $to),
            'treasury' => $this->dashboardService->treasuryBalance(),
            'lowStockCount' => $this->dashboardService->lowStockCount(),
            'todayQueue' => $this->dashboardService->todayQueue(),
            'filters' => compact('from', 'to'),
        ]);
    }
}
