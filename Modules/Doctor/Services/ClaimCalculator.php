<?php

namespace Modules\Doctor\Services;

use Carbon\Carbon;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\FeeType;
use Modules\Doctor\Models\Doctor;

class ClaimCalculator
{
    /**
     * Calculate earnings for a doctor in a given period.
     */
    public function calculate(Doctor $doctor, Carbon $from, Carbon $to): array
    {
        $bookings = Booking::where('doctor_id', $doctor->id)
            ->whereBetween('visit_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['confirmed', 'completed']) // Only confirmed/completed bookings count
            ->get();

        $totalRevenue = 0;
        $totalClaim = 0;
        $details = [];

        foreach ($bookings as $booking) {
            $revenue = (float) $booking->price;
            $claim = 0;

            if ($doctor->fee_type === FeeType::Percentage) {
                $claim = $revenue * ((float) $doctor->fee_value / 100);
            } elseif ($doctor->fee_type === FeeType::Fixed) {
                $claim = (float) $doctor->fee_value;
            } elseif ($doctor->fee_type === FeeType::Insurance) {
                // If insurance, claim is usually a percentage of the amount NOT covered by insurance,
                // or a different specific logic. For now, we'll treat it as percentage of total.
                $claim = $revenue * ((float) $doctor->fee_value / 100);
            }

            $totalRevenue += $revenue;
            $totalClaim += $claim;

            $details[] = [
                'booking_id' => $booking->id,
                'patient' => $booking->patient_name,
                'revenue' => $revenue,
                'claim' => $claim,
                'date' => $booking->visit_date->toDateString(),
            ];
        }

        return [
            'doctor_name' => $doctor->name,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'stats' => [
                'booking_count' => $bookings->count(),
                'total_revenue' => $totalRevenue,
                'total_claim' => $totalClaim,
            ],
            'details' => $details,
        ];
    }
}
