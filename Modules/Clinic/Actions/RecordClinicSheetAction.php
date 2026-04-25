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
