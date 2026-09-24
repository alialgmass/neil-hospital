<?php

namespace Modules\Doctor\Actions;

use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;

/**
 * Pays out a booking's delegated/anesthesia doctors on each cash payment —
 * the counterpart, for booking_doctor_delegations rows, of the primary
 * doctor's dues posting in PayBookingController. Each line is a flat fee
 * (not derived from the paid amount), so — mirroring FeeType::Fixed/
 * DoctorClaimsService::surgeryShareForPayment() — it is posted in full on
 * the booking's first payment only.
 */
class PostDelegatedDoctorDuesForPaymentAction
{
    public function __construct(
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
    ) {}

    public function execute(Booking $booking, bool $isFirstPayment, float $newPaidTotal): void
    {
        if (! $isFirstPayment) {
            return;
        }

        $delegations = BookingDoctorDelegation::where('booking_id', $booking->id)
            ->where('status', DelegationStatus::Pending)
            ->get();

        foreach ($delegations as $delegation) {
            $doctor = Doctor::find($delegation->doctor_id);

            if (! $doctor) {
                continue;
            }

            $amount = (float) $delegation->amount;
            $settled = $amount > 0 ? $doctor->settleDebtForBooking($booking->id, $amount) : 0.0;
            $netShare = $amount - $settled;

            if ($netShare > 0) {
                $this->autoPostDoctorDues->execute(
                    dept: $booking->dept,
                    amount: $netShare,
                    doctorName: $doctor->name,
                    reference: $booking->file_no,
                    date: $booking->visit_date->toDateString(),
                    idempotencyKey: "doctor_dues:delegate:{$delegation->id}:{$newPaidTotal}",
                );
            }

            $delegation->update(['status' => DelegationStatus::Settled]);
        }
    }
}
