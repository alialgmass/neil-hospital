<?php

namespace Modules\Clinic\Actions;

use App\Services\ActivityLogService;
use Modules\Booking\Actions\CreateBookingAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;

class ReferPatientAction
{
    public function __construct(
        private readonly ClinicSheetRepositoryInterface $clinicSheetRepository,
        private readonly CreateBookingAction $createBookingAction,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Set referral on the clinic sheet and optionally create a follow-up booking.
     *
     * @param  bool  $createFollowUp  Whether to auto-create a follow-up booking in target dept.
     */
    public function execute(
        string $bookingId,
        string $referralTo,
        int $referringUserId,
        bool $createFollowUp = false,
    ): void {
        $sheet = $this->clinicSheetRepository->findByBooking($bookingId);

        if ($sheet) {
            $this->clinicSheetRepository->createOrUpdate($bookingId, [
                ...$sheet->toArray(),
                'referral_to' => $referralTo,
            ]);
        }

        if ($createFollowUp) {
            $originalBooking = Booking::findOrFail($bookingId);

            $followUpData = BookingData::fromArray([
                'patient_name'  => $originalBooking->patient_name,
                'patient_phone' => $originalBooking->patient_phone,
                'patient_age'   => $originalBooking->patient_age,
                'national_id'   => $originalBooking->national_id,
                'gender'        => $originalBooking->gender,
                'dept'          => $referralTo,
                'doctor_id'     => $originalBooking->doctor_id,
                'visit_date'    => today()->addDay()->toDateString(),
                'pay_method'    => 'cash',
                'pay_status'    => 'unpaid',
                'status'        => 'waiting',
                'visit_note'    => "إحالة من العيادة — حجز #{$originalBooking->file_no}",
            ]);

            $this->createBookingAction->execute($followUpData, $referringUserId);
        }

        $this->activityLog->log(
            action:      'referred',
            module:      'clinic',
            recordId:    $bookingId,
            description: "إحالة المريض إلى: {$referralTo}",
        );
    }
}
