<?php

namespace Modules\Clinic\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Actions\DeleteMedicalExaminationAction;
use Modules\Clinic\Actions\SaveMedicalExaminationAction;
use Modules\Clinic\DTOs\MedicalExaminationData;
use Modules\Clinic\Http\Requests\SaveMedicalExaminationRequest;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Clinic\Services\MedicalExaminationService;

class MedicalExaminationController extends Controller
{
    public function __construct(
        private readonly MedicalExaminationService $examinationService,
        private readonly SaveMedicalExaminationAction $saveAction,
        private readonly DeleteMedicalExaminationAction $deleteAction,
    ) {}

    /** The examination of a visit: the form when it is new or a draft, read-only once finalized. */
    public function booking(Request $request, Booking $booking): Response
    {
        $examination = $booking->medicalExamination;

        return $this->renderForm($request, $booking, $examination, readOnly: false);
    }

    /** A specific (typically previous) examination, always read-only. */
    public function show(Request $request, MedicalExamination $examination): Response
    {
        return $this->renderForm($request, $examination->booking, $examination, readOnly: true);
    }

    public function store(SaveMedicalExaminationRequest $request, Booking $booking): RedirectResponse
    {
        $examination = $this->saveAction->execute(
            $booking,
            MedicalExaminationData::fromArray($request->validated()),
            $request->user(),
        );

        return to_route('examinations.booking', $booking)
            ->with('success', $examination->isFinalized() ? 'تم اعتماد الفحص الطبي.' : 'تم حفظ الفحص الطبي كمسودة.');
    }

    public function update(SaveMedicalExaminationRequest $request, MedicalExamination $examination): RedirectResponse
    {
        $examination = $this->saveAction->execute(
            $examination->booking,
            MedicalExaminationData::fromArray($request->validated()),
            $request->user(),
            $examination,
        );

        return back()->with('success', $examination->isFinalized() ? 'تم اعتماد الفحص الطبي.' : 'تم حفظ التعديلات.');
    }

    public function destroy(MedicalExamination $examination): RedirectResponse
    {
        Gate::authorize('delete', $examination);

        $bookingId = $examination->booking_id;

        $this->deleteAction->execute($examination);

        return to_route('examinations.booking', $bookingId)->with('success', 'تم حذف مسودة الفحص الطبي.');
    }

    public function print(MedicalExamination $examination): Response
    {
        return Inertia::render('clinic/ExaminationPrint', [
            'patient' => $this->examinationService->patientSummary($examination->booking),
            'examination' => $this->examinationService->loadForDisplay($examination),
            'options' => $this->examinationService->formOptions(),
        ]);
    }

    private function renderForm(Request $request, Booking $booking, ?MedicalExamination $examination, bool $readOnly): Response
    {
        return Inertia::render('clinic/Examination', [
            'patient' => $this->examinationService->patientSummary($booking),
            'examination' => $examination ? $this->examinationService->loadForDisplay($examination) : null,
            'read_only' => $readOnly,
            'prefill' => $examination ? [] : $this->examinationService->prefillFromClinicSheet($booking),
            'default_doctor_id' => $this->examinationService->doctorForUser($request->user())?->id ?? $booking->doctor_id,
            'previous_examinations' => $this->examinationService->previousExaminations($booking),
            'options' => $this->examinationService->formOptions(),
        ]);
    }
}
