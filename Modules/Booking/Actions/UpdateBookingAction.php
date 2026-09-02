<?php

namespace Modules\Booking\Actions;

use App\Enums\Department;
use App\Services\ActivityLogService;
use Illuminate\Database\QueryException;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;
use Modules\Surgery\Actions\ScheduleSurgeryAction;
use Modules\Surgery\DTOs\SurgeryData;

class UpdateBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ScheduleSurgeryAction $scheduleSurgery,
        private readonly ActivityLogService $activityLog,
        private readonly AutoPostBookingPaymentAction $autoPost,
    ) {}

    public function execute(string $id, BookingData $data): Booking
    {
        $old = $this->bookingService->findOrFail($id);

        $booking = $this->bookingService->update($id, $data);

        $this->syncInsuranceClaim($booking, $old, $data);

        if (in_array($data->dept, [Department::Surgery, Department::Lasik])) {
            $this->scheduleSurgery->execute(new SurgeryData(
                bookingId: $booking->id,
                dept: $data->dept,
                orBedId: $data->bedId,
                surgeonId: $data->doctorId,
                eye: $data->eyeSide,
            ));
        }

        // Auto-post accounting entries when payment is first confirmed
        if ($old->pay_status !== PayStatus::Paid && $booking->pay_status === PayStatus::Paid) {
            $this->autoPost->execute($booking);
        }

        $this->activityLog->log(
            action: 'updated',
            module: 'booking',
            recordId: $booking->id,
            description: "تعديل حجز: {$booking->patient_name} — {$booking->file_no}",
            oldValues: $old->toArray(),
            newValues: $booking->toArray(),
        );

        return $booking;
    }

    /**
     * Keep the booking's insurance claim in sync with edits.
     * - No claim yet and a company is now set → create one (mirrors CreateBookingAction).
     * - A claim already exists and is still a draft → refresh it in place so a changed
     *   company or amount doesn't leave a stale claim behind.
     * - A claim exists but has already moved past draft (submitted/approved/paid) → leave
     *   it untouched; it's already part of the insurance workflow and must not be
     *   silently rewritten.
     */
    private function syncInsuranceClaim(Booking $booking, Booking $old, BookingData $data): void
    {
        if (! $data->insCompanyId) {
            return;
        }

        $patientShare = max(0, $data->price - $data->discount - $data->insAmount);

        $claimAttributes = [
            'insurance_company_id' => $data->insCompanyId,
            'service_id' => $data->serviceId,
            'patient_name' => $data->patientName,
            'file_no' => $booking->file_no,
            'service_name' => $data->serviceName ?? '',
            'invoice_amount' => $data->price,
            'discount' => $data->discount,
            'insurance_share' => $data->insAmount,
            'patient_share' => $patientShare,
            'service_date' => $data->visitDate,
        ];

        $claim = InsuranceClaim::where('booking_id', $booking->id)->first();

        if (! $claim) {
            try {
                InsuranceClaim::create([
                    ...$claimAttributes,
                    'booking_id' => $booking->id,
                    'approved_amount' => 0,
                    'paid_amount' => 0,
                    'status' => DraftState::class,
                    'claim_date' => today()->toDateString(),
                    'created_by' => $old->created_by,
                ]);
            } catch (QueryException $e) {
                // A concurrent request already created the claim (unique booking_id
                // constraint) — nothing to do, it'll pick up this data on the next edit.
                // Any other DB failure should still surface.
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }

            return;
        }

        if ($claim->status->equals(DraftState::class)) {
            $claim->update($claimAttributes);
        }
    }
}
