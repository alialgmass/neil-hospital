<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\ShiftHandover;
use Modules\HR\Services\HRService;

class ShiftHandoverController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $filters = request()->only(['date', 'shift_id', 'status']);

        $stats = [
            'today' => ShiftHandover::whereDate('handover_date', today())->count(),
            'pending' => ShiftHandover::where('status', 'pending')->count(),
            'accepted' => ShiftHandover::where('status', 'accepted')->count(),
        ];

        return Inertia::render('hr/ShiftHandover', [
            'handovers' => $this->hr->listHandovers($filters, 25),
            'shifts' => $this->hr->listShifts(),
            'employees' => $this->hr->getActiveEmployees(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'from_employee_id' => ['required', 'exists:employees,id'],
            'to_employee_id' => ['required', 'exists:employees,id', 'different:from_employee_id'],
            'handover_date' => ['required', 'date'],
            'handover_time' => ['nullable', 'string'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->hr->createHandover($data);

        return back()->with('success', 'تم تسجيل تسليم الوردية بنجاح.');
    }

    public function accept(string $id): RedirectResponse
    {
        $this->hr->acceptHandover($id);

        return back()->with('success', 'تم قبول تسليم الوردية.');
    }
}
