<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Enums\HandoverStatus;
use Modules\HR\Models\ShiftHandover;
use Modules\HR\Requests\StoreHandoverRequest;
use Modules\HR\Services\HRService;

class ShiftHandoverController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['date', 'shift_id', 'status']);

        $stats = [
            'today' => ShiftHandover::whereDate('handover_date', today())->count(),
            'pending' => ShiftHandover::where('status', HandoverStatus::Pending)->count(),
            'accepted' => ShiftHandover::where('status', HandoverStatus::Accepted)->count(),
        ];

        return Inertia::render('hr/ShiftHandover', [
            'handovers' => $this->hr->listHandovers($filters, 25),
            'shifts' => $this->hr->listShifts(),
            'employees' => $this->hr->getActiveEmployees(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(StoreHandoverRequest $request): RedirectResponse
    {
        $this->hr->createHandover($request->validated());

        return back()->with('success', 'تم تسجيل تسليم الوردية بنجاح.');
    }

    public function accept(string $id): RedirectResponse
    {
        $this->hr->acceptHandover($id);

        return back()->with('success', 'تم قبول تسليم الوردية.');
    }
}
