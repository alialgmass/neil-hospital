<?php

namespace Modules\Doctor\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Doctor\Actions\RecordDoctorPaymentAction;
use Modules\Doctor\Services\DoctorClaimsService;

class DoctorClaimsController extends Controller
{
    public function __construct(
        private readonly DoctorClaimsService $claimsService,
        private readonly RecordDoctorPaymentAction $recordPayment,
    ) {}

    public function index(): Response
    {
        $from = request('from');
        $to = request('to');

        return Inertia::render('doctors/Claims', [
            'summaries' => $this->claimsService->summarizeAll($from, $to),
            'claims' => null,
            'filters' => compact('from', 'to'),
        ]);
    }

    public function calculate(): Response
    {
        $doctorId = request('doctor_id');
        $from = request('from');
        $to = request('to');

        return Inertia::render('doctors/Claims', [
            'summaries' => $this->claimsService->summarizeAll($from, $to),
            'claims' => $doctorId
                ? $this->claimsService->calculateClaims($doctorId, $from, $to)
                : null,
            'filters' => ['doctor_id' => $doctorId, 'from' => $from, 'to' => $to],
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:cash,transfer'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->recordPayment->execute($data);

        return back()->with('success', 'تم تسجيل الدفعة بنجاح.');
    }
}
