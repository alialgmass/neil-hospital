<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\ShiftHandover;
use Modules\HR\Requests\StoreShiftRequest;
use Modules\HR\Services\HRService;

class ShiftController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $recentHandovers = ShiftHandover::with(['shift:id,name', 'fromEmployee:id,name', 'toEmployee:id,name'])
            ->orderByDesc('handover_date')
            ->limit(10)
            ->get();

        return Inertia::render('hr/Shifts', [
            'shifts' => $this->hr->listShifts(),
            'recent_handovers' => $recentHandovers,
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        $this->hr->createShift($request->validated());

        return back()->with('success', 'تم إضافة الوردية بنجاح.');
    }

    public function update(StoreShiftRequest $request, string $id): RedirectResponse
    {
        $this->hr->updateShift($id, $request->validated());

        return back()->with('success', 'تم تعديل الوردية بنجاح.');
    }
}
