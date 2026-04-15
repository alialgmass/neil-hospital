<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
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

        $bookings = DB::table('bookings')
            ->whereBetween('visit_date', [$from, $to]);

        $totalRevenue = (float) (clone $bookings)->where('pay_status', '!=', 'unpaid')->sum('price');
        $paidCount = (clone $bookings)->where('pay_status', 'paid')->count();
        $pendingAmount = (float) (clone $bookings)->where('pay_status', 'unpaid')->sum('price');
        $todayRevenue = (float) DB::table('bookings')
            ->whereDate('visit_date', today())
            ->where('pay_status', '!=', 'unpaid')
            ->sum('price');

        return Inertia::render('reports/Income', [
            'from' => $from,
            'to' => $to,
            'stats' => compact('totalRevenue', 'paidCount', 'pendingAmount', 'todayRevenue'),
            'revenueByDept' => $this->dashboardService->revenueByDept($from, $to),
            'revenueByDoc' => $this->dashboardService->revenueByDoctor($from, $to),
        ]);
    }
}
