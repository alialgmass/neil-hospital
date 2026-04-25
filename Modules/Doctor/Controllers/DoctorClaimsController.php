<?php

namespace Modules\Doctor\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Doctor\Services\DoctorClaimsService;

class DoctorClaimsController extends Controller
{
    public function __construct(
        private readonly DoctorClaimsService $claimsService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        return Inertia::render('doctors/Claims', [
            'doctors' => $this->claimsService->doctors(),
            'filters' => request()->only(['doctor_id', 'from', 'to']),
        ]);
    }

    public function calculate(): Response
    {
        $doctorId = request('doctor_id');
        $from = request('from');
        $to = request('to');

        return Inertia::render('doctors/Claims', [
            'doctors' => $this->claimsService->doctors(),
            'claims' => $doctorId && $from && $to
                ? $this->claimsService->calculateClaims($doctorId, $from, $to)
                : null,
            'filters' => compact('doctor_id', 'from', 'to'),
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:cash,transfer'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->claimsService->recordPayment($data);

        $this->activityLog->log(
            action: 'dr_payment',
            module: 'doctors',
            recordId: $payment->id,
            description: "دفعة للدكتور: {$payment->amount} ج.م",
        );

        return back()->with('success', 'تم تسجيل الدفعة بنجاح.');
    }
}
