<?php

namespace Modules\Clinic\Actions;

use App\Services\ActivityLogService;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Clinic\DTOs\ClinicSheetData;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;

class RecordClinicSheetAction
{
    public function __construct(
        private readonly ClinicSheetRepositoryInterface $clinicSheetRepository,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(ClinicSheetData $data): ClinicSheet
    {
        $sheet = $this->clinicSheetRepository->createOrUpdate($data->bookingId, [
            'doctor_id' => $data->doctorId,
            'chief_complaint' => $data->chiefComplaint,
            'visual_acuity_od' => $data->visualAcuityOd,
            'visual_acuity_os' => $data->visualAcuityOs,
            'iop_od' => $data->iopOd,
            'iop_os' => $data->iopOs,
            'anterior_segment' => $data->anteriorSegment,
            'posterior_segment' => $data->posteriorSegment,
            'diagnosis' => $data->diagnosis,
            'plan' => $data->plan,
            'referral_to' => $data->referralTo,
            'notes' => $data->notes,
            'patient_contact' => $data->patientContact,
            'allergies_status' => $data->allergiesStatus,
            'allergies_specify' => $data->allergiesSpecify,
            'current_medications' => $data->currentMedications,
            'plan_medications' => $data->planMedications,
            'visual_exam' => $data->visualExam,
            'eye_exam_grid' => $data->eyeExamGrid,
            'plan_education' => $data->planEducation,
            'plan_followup' => $data->planFollowup,
            'nursing_assessment' => $data->nursingAssessment,
            'nursing_history_answers' => $data->nursingHistoryAnswers,
            'fall_screening' => $data->fallScreening,
            'fall_screening_score' => array_sum(array_map('intval', $data->fallScreening)),
            'drops_given' => $data->dropsGiven,
            'critical_results' => $data->criticalResults,
            'nursing_notes' => $data->nursingNotes,
            'nurse_signature_name' => $data->nurseSignatureName,
            'evaluator_name' => $data->evaluatorName,
            'evaluated_at' => $data->evaluatedAt,
        ]);

        // Auto-advance booking to completed
        $this->bookingRepository->updateStatus($data->bookingId, 'completed');

        $this->activityLog->log(
            action: 'clinic_sheet_recorded',
            module: 'clinic',
            recordId: $sheet->id,
            description: "تسجيل كشف طبي للحجز: {$data->bookingId}",
        );

        return $sheet;
    }
}
