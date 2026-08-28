<?php

namespace Modules\Clinic\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
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
     * Set referral on the clinic sheet and optionally create a same-day
     * follow-up booking in the target department. The follow-up gets its
     * own file_no (file_no is unique per booking — it can't share the
     * original's) but its visit_note back-references the original visit,
     * and patient history is still discoverable via patientHistory()'s
     * name/phone match.
     *
     * @param  bool  $createFollowUp  Whether to auto-create a follow-up booking in target dept.
     */
    public function execute(
        string $bookingId,
        string $referralTo,
        int $referringUserId,
        bool $createFollowUp = false,
    ): void {
        $originalBooking = Booking::findOrFail($bookingId);

        if ($referralTo === $originalBooking->dept->value) {
            throw ValidationException::withMessages([
                'referral_to' => 'لا يمكن توجيه المريض إلى نفس القسم الحالي.',
            ]);
        }

        $sheet = $this->clinicSheetRepository->findByBooking($bookingId);

        $this->clinicSheetRepository->createOrUpdate($bookingId, [
            ...($sheet?->toArray() ?? []),
            'referral_to' => $referralTo,
        ]);

        if ($createFollowUp) {
            $followUpData = BookingData::fromArray([
                'patient_name' => $originalBooking->patient_name,
                'patient_phone' => $originalBooking->patient_phone,
                'patient_age' => $originalBooking->patient_age,
                'national_id' => $originalBooking->national_id,
                'gender' => $originalBooking->gender,
                'kinship_degree' => $originalBooking->kinship_degree,
                'dept' => $referralTo,
                'doctor_id' => $originalBooking->doctor_id,
                // Same-day: the patient is being routed to the next stop of
                // *this* visit, not scheduled for a future date.
                'visit_date' => today()->toDateString(),
                'pay_method' => 'cash',
                'pay_status' => 'unpaid',
                'status' => 'waiting',
                'visit_note' => "إحالة من العيادة — حجز #{$originalBooking->file_no}",
            ]);

            $this->createBookingAction->execute($followUpData, $referringUserId);
        }

        $this->activityLog->log(
            action: 'referred',
            module: 'clinic',
            recordId: $bookingId,
            description: "إحالة المريض إلى: {$referralTo}",
        );
    }
}
