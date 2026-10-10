<?php

namespace Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Enums\CallDirection;
use Modules\Booking\Enums\CallOutcome;
use Modules\Booking\Enums\CallReason;
use Modules\Booking\Enums\PreBookingStatus;
use Modules\Booking\Http\Requests\StoreCallLogRequest;
use Modules\Booking\Http\Requests\StorePreBookingRequest;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Booking\Services\CallCenterService;
use Modules\Doctor\Models\Doctor;

class CallCenterController extends Controller
{
    public function __construct(private readonly CallCenterService $callCenter) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'reminder_date' => 'nullable|date',
            'pre_status' => ['nullable', 'in:'.implode(',', array_column(PreBookingStatus::cases(), 'value'))],
        ]);

        $reminderDate = $validated['reminder_date'] ?? today()->addDay()->toDateString();
        $preStatus = PreBookingStatus::tryFrom($validated['pre_status'] ?? PreBookingStatus::Pending->value);

        return Inertia::render('callcenter/Index', [
            'filters' => [
                'date' => $validated['date'] ?? null,
                'search' => $validated['search'] ?? null,
                'reminder_date' => $reminderDate,
                'pre_status' => $preStatus?->value,
            ],
            'calls' => $this->callCenter->calls($validated),
            'preBookings' => $this->callCenter->preBookings($preStatus),
            'reminders' => $this->callCenter->reminders($reminderDate),
            'followUps' => $this->callCenter->dueFollowUps(),
            'services' => Service::active()->orderBy('name')->get(['id', 'name', 'dept']),
            'doctors' => Doctor::orderBy('name')->get(['id', 'name']),
            'insuranceCompanies' => InsuranceCompany::orderBy('name')->get(['id', 'name']),
            'options' => [
                'directions' => $this->options(CallDirection::cases()),
                'reasons' => $this->options(CallReason::cases()),
                'outcomes' => $this->options(CallOutcome::cases()),
                'preStatuses' => $this->options(PreBookingStatus::cases()),
            ],
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json(mb_strlen($term) < 2 ? [] : $this->callCenter->lookupPatients($term));
    }

    public function storeCall(StoreCallLogRequest $request): RedirectResponse
    {
        $this->callCenter->recordCall($request->validated(), $request->user()->id);

        return back()->with('success', 'تم تسجيل المكالمة.');
    }

    public function storePreBooking(StorePreBookingRequest $request): RedirectResponse
    {
        $preBooking = $this->callCenter->createPreBooking($request->validated(), $request->user()->id);

        return back()->with('success', "تم تسجيل الحجز المبدئي لـ {$preBooking->patient_name} وإرساله للاستقبال.");
    }

    public function cancelPreBooking(Request $request, string $id): RedirectResponse
    {
        DB::transaction(fn () => $this->callCenter->cancelPreBooking($id, $request->user()->id));

        return back()->with('success', 'تم إلغاء الحجز المبدئي.');
    }

    /**
     * @param  array<int, CallDirection|CallReason|CallOutcome|PreBookingStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
