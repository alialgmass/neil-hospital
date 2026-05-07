<?php

namespace Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpiryReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = max(1, (int) $request->input('days', 60));
        $category = $request->input('category');
        $statusFilter = $request->input('status');

        $today = today()->toDateString();
        $threshold = today()->addDays($days)->toDateString();

        $allWithExpiry = DB::table('inventory')
            ->whereNotNull('expiry_date')
            ->get(['expiry_date']);

        $expiredCount = $allWithExpiry->where('expiry_date', '<', $today)->count();
        $soonCount = $allWithExpiry->filter(
            fn ($i) => $i->expiry_date >= $today && $i->expiry_date <= $threshold
        )->count();

        $rows = DB::table('inventory')
            ->whereNotNull('expiry_date')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('expiry_date')
            ->get(['id', 'name', 'code', 'category', 'quantity', 'unit', 'unit_cost', 'expiry_date'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'category' => $item->category,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_cost' => (float) $item->unit_cost,
                'expiry_date' => $item->expiry_date,
                'status' => $item->expiry_date < $today ? 'expired'
                    : ($item->expiry_date <= $threshold ? 'expiring_soon' : 'ok'),
                'days_left' => (int) floor((strtotime($item->expiry_date) - time()) / 86400),
            ])
            ->when(
                $statusFilter && $statusFilter !== 'all',
                fn ($c) => $c->where('status', $statusFilter)
            )
            ->values()
            ->all();

        $categories = DB::table('inventory')
            ->whereNotNull('category')
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('reports/ExpiryReport', [
            'data' => [
                'rows' => $rows,
                'expired_count' => $expiredCount,
                'soon_count' => $soonCount,
                'total_with_expiry' => $allWithExpiry->count(),
            ],
            'filters' => [
                'days' => $days,
                'category' => $category,
                'status_filter' => $statusFilter,
            ],
            'categories' => $categories,
        ]);
    }
}
