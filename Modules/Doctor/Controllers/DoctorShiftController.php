<?php

namespace Modules\Doctor\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Doctor\Services\DoctorService;

class DoctorShiftController extends Controller
{
    public function __construct(private readonly DoctorService $doctorService) {}

    public function index(): Response
    {
        return Inertia::render('doctors/Shifts', [
            'shifts' => $this->doctorService->shifts(request()->only(['doctor_id', 'date'])),
            'doctors' => $this->doctorService->allActive(),
            'filters' => request()->only(['doctor_id', 'date']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'shift_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->doctorService->openShift($data['doctor_id'], $data['shift_date'], $data['notes'] ?? null);

        return back()->with('success', 'تم فتح الوردية بنجاح.');
    }

    public function close(string $id): RedirectResponse
    {
        $this->doctorService->closeShift($id);

        return back()->with('success', 'تم إغلاق الوردية.');
    }

    public function handover(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
        ]);

        $this->doctorService->handoverShift($id, $data);

        return back()->with('success', 'تم تسليم الوردية بنجاح.');
    }

    public function show(string $id): Response
    {
        $summary = $this->doctorService->shiftSummary($id);
        $shift = $summary['shift'];

        return Inertia::render('doctors/ShiftHandover', [
            'shift' => $shift,
            'pending_bookings' => $this->doctorService->getPendingBookingsForShift($shift->doctor_id, $shift->shift_date),
        ] + $summary);
    }
}
