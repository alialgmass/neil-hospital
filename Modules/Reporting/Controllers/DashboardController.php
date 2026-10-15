<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Reporting\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    /**
     * Financial statistics (today's revenue, treasury balance, revenue by
     * department/doctor) are admin-only; everyone else sees the queue and
     * stock alerts. Non-admins never receive the figures in the payload.
     */
    public function index(): Response
    {
        $from = request('from');
        $to = request('to');
        $canViewStats = (bool) request()->user()?->hasRole('admin');

        return Inertia::render('Dashboard', [
            'canViewStats' => $canViewStats,
            'todayStats' => $canViewStats ? $this->dashboardService->todayStats() : null,
            'revenueByDept' => $canViewStats ? $this->dashboardService->revenueByDept($from, $to) : [],
            'revenueByDoc' => $canViewStats ? $this->dashboardService->revenueByDoctor($from, $to) : [],
            'treasury' => $canViewStats ? $this->dashboardService->treasuryBalance() : null,
            'lowStockCount' => $this->dashboardService->lowStockCount(),
            'todayQueue' => $this->dashboardService->todayQueue(),
            'filters' => compact('from', 'to'),
        ]);
    }
}
