<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Services\HRService;

class AttendanceController extends Controller
{
    public function __construct(private readonly HRService $hr) {}

    public function index(): Response
    {
        $date = request('date', now()->toDateString());

        return Inertia::render('hr/Attendance', [
            'rows' => $this->hr->getDailyAttendance($date),
            'shifts' => $this->hr->listShifts(),
            'filters' => ['date' => $date],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'rows' => ['required', 'array'],
            'rows.*.employee_id' => ['required', 'exists:employees,id'],
            'rows.*.status' => ['required', 'string'],
            'rows.*.check_in' => ['nullable', 'string'],
            'rows.*.check_out' => ['nullable', 'string'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes' => ['nullable', 'string'],
        ]);

        $this->hr->saveDailyAttendance($data['date'], $data['rows'], $data['shift_id'] ?? null);

        return back()->with('success', 'تم تسجيل الحضور بنجاح.');
    }
}
