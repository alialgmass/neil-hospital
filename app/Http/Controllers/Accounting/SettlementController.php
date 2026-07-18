<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Accounting\Services\MonthlySettlementService;

class SettlementController extends Controller
{
    public function __construct(
        private readonly MonthlySettlementService $settlementService
    ) {}

    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $preview = $this->settlementService->preview($month);

        return Inertia::render('accounting/Settlement', [
            'month' => $month,
            'preview' => $preview,
        ]);
    }

    public function lock(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $this->settlementService->lock($request->month, $request->notes);

        return back()->with('success', 'تم إغلاق الشهر وتثبيت الأرقام بنجاح');
    }
}
