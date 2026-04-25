<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Http\Requests\StoreTreasuryRequest;
use Modules\Accounting\Services\TreasuryService;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['type', 'from', 'to']);

        return Inertia::render('treasury/Index', [
            'entries' => $this->treasuryService->list($filters, 30),
            'balance' => $this->treasuryService->balance(),
            'todayNet' => $this->treasuryService->todayNet(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreTreasuryRequest $request): RedirectResponse
    {
        $entry = $this->treasuryService->record($request->validated());

        $this->activityLog->log(
            action: 'treasury_entry',
            module: 'treasury',
            recordId: $entry->id,
            description: "{$entry->type->label()}: {$entry->description} — {$entry->amount} ج.م",
        );

        return back()->with('success', 'تم تسجيل حركة الخزنة بنجاح.');
    }
}
