<?php

namespace Modules\Doctor\Actions;

use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;

/**
 * Keeps a booking's delegated-doctor / anesthesia-doctor lines
 * (booking_doctor_delegations) in sync with what was submitted on the
 * surgery/lasik schedule form — a full replace of the non-settled rows
 * rather than a diff, mirroring SurgeryService::recordSupplies().
 *
 * A settled row (already paid out) is never touched here.
 */
class SyncBookingDoctorDelegationsAction
{
    /**
     * @param  array<int, array{doctor_id: string, role: string, service_id: ?string, service_name: string, amount: float}>  $lines
     */
    public function execute(Booking $booking, array $lines): void
    {
        // A settled row has already been paid out — resubmitting the same
        // form (e.g. editing an unrelated field) must not recreate it as a
        // new, duplicate pending line.
        $settledSignatures = BookingDoctorDelegation::where('booking_id', $booking->id)
            ->where('status', DelegationStatus::Settled)
            ->get()
            ->map(fn (BookingDoctorDelegation $d) => $this->signature($d->doctor_id, $d->role->value, $d->service_id, (float) $d->amount))
            ->all();

        BookingDoctorDelegation::where('booking_id', $booking->id)
            ->where('status', '!=', DelegationStatus::Settled->value)
            ->delete();

        foreach ($lines as $line) {
            $signature = $this->signature($line['doctor_id'], $line['role'], $line['service_id'] ?? null, (float) $line['amount']);

            if (in_array($signature, $settledSignatures, true)) {
                continue;
            }

            BookingDoctorDelegation::create([
                'booking_id' => $booking->id,
                'doctor_id' => $line['doctor_id'],
                'role' => $line['role'],
                'service_id' => $line['service_id'] ?? null,
                'service_name' => $line['service_name'],
                'amount' => $line['amount'],
                'status' => DelegationStatus::Pending,
            ]);
        }
    }

    private function signature(string $doctorId, string $role, ?string $serviceId, float $amount): string
    {
        return implode('|', [$doctorId, $role, $serviceId ?? '', number_format($amount, 2, '.', '')]);
    }
}
