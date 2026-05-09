<?php

namespace Modules\HR\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Requests\StoreAttendanceRequest;
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

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->hr->saveDailyAttendance($data['date'], $data['rows'], $data['shift_id'] ?? null);

        return back()->with('success', 'تم تسجيل الحضور بنجاح.');
    }
}
