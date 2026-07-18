<?php

namespace Modules\Clinic\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Clinic\Actions\RecordClinicSheetAction;
use Modules\Clinic\DTOs\ClinicSheetData;
use Modules\Clinic\Http\Requests\StoreClinicSheetRequest;
use Modules\Clinic\Services\ClinicService;

class ClinicController extends Controller
{
    public function __construct(
        private readonly ClinicService $clinicService,
        private readonly RecordClinicSheetAction $recordSheetAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('clinic/Index', [
            'queue' => $this->clinicService->getTodaysQueue(request('date')),
            'date' => request('date', today()->toDateString()),
        ]);
    }

    public function show(string $bookingId): Response
    {
        ['booking' => $booking, 'history' => $history] = $this->clinicService->getPatientHistory($bookingId);

        return Inertia::render('clinic/Patient', [
            'booking' => $booking->load(['doctor:id,name', 'clinicSheet']),
            'history' => $history,
        ]);
    }

    public function storeSheet(StoreClinicSheetRequest $request, string $bookingId): RedirectResponse
    {
        $data = ClinicSheetData::fromArray([
            ...$request->validated(),
            'booking_id' => $bookingId,
        ]);

        $this->recordSheetAction->execute($data);

        return back()->with('success', 'تم تسجيل الكشف الطبي بنجاح.');
    }
}
