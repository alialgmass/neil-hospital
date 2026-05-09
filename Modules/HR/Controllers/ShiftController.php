<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\ShiftHandover;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $this->hr->createShift($data);

        return back()->with('success', 'تم إضافة الوردية بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $this->hr->updateShift($id, $data);

        return back()->with('success', 'تم تعديل الوردية بنجاح.');
    }
}
