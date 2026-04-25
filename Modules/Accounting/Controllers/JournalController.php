<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Http\Requests\StoreJournalRequest;
use Modules\Accounting\Services\JournalService;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['from', 'to', 'source']);

        return Inertia::render('journal/Index', [
            'entries' => $this->journalService->list($filters, 30),
            'accounts' => $this->journalService->accounts(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreJournalRequest $request): RedirectResponse
    {
        $entry = $this->journalService->record($request->validated());

        $this->activityLog->log(
            action: 'journal_entry',
            module: 'accounting',
            recordId: $entry->id,
            description: "قيد يومية: {$entry->description} — {$entry->amount} ج.م",
        );

        return back()->with('success', 'تم تسجيل القيد بنجاح.');
    }
}
