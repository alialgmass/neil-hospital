<?php

namespace Modules\Doctor\Actions;

use Illuminate\Support\Collection;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Doctor\Models\DoctorPayment;

class SettleDoctorEntitlementsAction
{
    public function execute(DoctorPayment $payment): void
    {
        $pendingEntitlements = DoctorEntitlement::query()
            ->where('doctor_id', $payment->doctor_id)
            ->where('status', EntitlementStatus::Pending)
            ->with('booking:id,visit_date')
            ->lockForUpdate()
            ->get();

        $pendingDelegations = BookingDoctorDelegation::query()
            ->where('doctor_id', $payment->doctor_id)
            ->where('status', DelegationStatus::Pending)
            ->with('booking:id,visit_date')
            ->lockForUpdate()
            ->get();

        if ($pendingEntitlements->isEmpty() && $pendingDelegations->isEmpty()) {
            return;
        }

        $settledAmount = (float) DoctorEntitlement::query()
            ->where('doctor_id', $payment->doctor_id)
            ->where('status', EntitlementStatus::Settled)
            ->sum('amount');

        $settledAmount += (float) BookingDoctorDelegation::query()
            ->where('doctor_id', $payment->doctor_id)
            ->where('status', DelegationStatus::Settled)
            ->sum('amount');

        $availableAmount = (float) DoctorPayment::query()
            ->where('doctor_id', $payment->doctor_id)
            ->sum('amount') - $settledAmount;

        if ($availableAmount <= 0) {
            return;
        }

        $this->sortByVisitDate($pendingEntitlements->merge($pendingDelegations))
            ->each(function (object $due) use (&$availableAmount): void {
                $amount = (float) $due->amount;

                if ($amount <= 0 || $availableAmount < $amount) {
                    return;
                }

                $due->update([
                    'status' => $due instanceof DoctorEntitlement
                        ? EntitlementStatus::Settled
                        : DelegationStatus::Settled,
                ]);

                $availableAmount = round($availableAmount - $amount, 2);
            });
    }

    /**
     * @param  Collection<int, DoctorEntitlement|BookingDoctorDelegation>  $dues
     * @return Collection<int, DoctorEntitlement|BookingDoctorDelegation>
     */
    private function sortByVisitDate(Collection $dues): Collection
    {
        return $dues->sortBy(fn (object $due): array => [
            $due->booking?->visit_date?->toDateString() ?? '9999-12-31',
            $due->created_at?->toDateTimeString() ?? '',
            (string) $due->getKey(),
        ])->values();
    }
}
