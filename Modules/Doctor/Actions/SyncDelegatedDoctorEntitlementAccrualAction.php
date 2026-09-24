<?php

namespace Modules\Doctor\Actions;

use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Actions\AutoPostInsuranceDoctorCashPaymentAction;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Enums\EntitlementSource;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;

/**
 * Insurance/contract counterpart of PostDelegatedDoctorDuesForPaymentAction:
 * cash bookings pay a delegated/anesthesia doctor at the first cash
 * payment, but insurance/contract bookings never reach that flow — like
 * the primary doctor's entitlement (SyncDoctorEntitlementAction), their fee
 * accrues immediately when the booking is (re)saved as insurance/contract.
 *
 * A pending delegation line is settled (and its accrual posted) exactly
 * once; a settled line is left untouched.
 */
class SyncDelegatedDoctorEntitlementAccrualAction
{
    public function __construct(
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
        private readonly AutoPostInsuranceDoctorCashPaymentAction $autoPostInsuranceDoctorCash,
    ) {}

    public function execute(Booking $booking): void
    {
        $source = EntitlementSource::fromPayMethod($booking->pay_method);

        if ($source === null) {
            // Cash/card — settled at payment time instead, see
            // PostDelegatedDoctorDuesForPaymentAction.
            return;
        }

        $delegations = BookingDoctorDelegation::where('booking_id', $booking->id)
            ->where('status', DelegationStatus::Pending)
            ->get();

        foreach ($delegations as $delegation) {
            $doctor = Doctor::find($delegation->doctor_id);
            $amount = (float) $delegation->amount;

            if (! $doctor || $amount <= 0) {
                continue;
            }

            // Prefixed "doctor_delegation_entitlement" (not "doctor_entitlement")
            // so SyncDoctorEntitlementAction::reverseAccrual()'s LIKE match on
            // the primary doctor's own keys never sweeps these up too.
            $key = "doctor_delegation_entitlement:{$source->value}:{$delegation->id}";

            if ($source === EntitlementSource::Insurance) {
                $this->autoPostInsuranceDoctorCash->execute(
                    amount: $amount,
                    doctorName: $doctor->name,
                    reference: $booking->file_no,
                    date: $booking->visit_date?->toDateString(),
                    idempotencyKey: $key,
                );
            } else {
                $this->autoPostDoctorDues->execute(
                    dept: $booking->dept,
                    amount: $amount,
                    doctorName: $doctor->name,
                    reference: $booking->file_no,
                    date: $booking->visit_date?->toDateString(),
                    idempotencyKey: $key,
                );
            }

            $delegation->update(['status' => DelegationStatus::Settled]);
        }
    }
}
