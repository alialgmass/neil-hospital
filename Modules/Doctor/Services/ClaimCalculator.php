<?php

namespace Modules\Doctor\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->with('surgery')
            ->get();

        $entitlements = DB::table('doctor_entitlements')
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['pending', 'settled'])
            ->pluck('amount', 'booking_id');

        $totalRevenue = 0;
        $totalClaim = 0;
        $details = [];

        foreach ($bookings as $booking) {
            $revenue = (float) $booking->price;

            if ($entitlements->has($booking->id)) {
                // Insurance / contract booking: fixed amount from the persisted
                // entitlement, never re-computed here (avoids double counting).
                $claim = (float) $entitlements[$booking->id];
            } elseif ($doctor->fee_type === FeeType::Percentage) {
                $claim = $revenue * ((float) $doctor->fee_value / 100);
            } elseif ($doctor->fee_type === FeeType::Fixed) {
                $claim = (float) $doctor->fee_value;
            } elseif ($doctor->fee_type === FeeType::Insurance) {
                // If insurance, claim is usually a percentage of the amount NOT covered by insurance,
                // or a different specific logic. For now, we'll treat it as percentage of total.
                $claim = $revenue * ((float) $doctor->fee_value / 100);
            } else {
                $claim = 0;
            }

            $totalRevenue += $revenue;
            $totalClaim += $claim;

            $dept = $booking->dept->value;
            $row = [
                'booking_id' => $booking->id,
                'patient' => $booking->patient_name,
                'dept' => $dept,
                'revenue' => $revenue,
                'claim' => $claim,
                'date' => $booking->visit_date->toDateString(),
            ];

            if (in_array($dept, ['surgery', 'lasik']) && $booking->surgery) {
                $row['supplies'] = $booking->surgery->supplies_used ?? [];
                $row['supply_total'] = (float) $booking->surgery->supply_total;
            }

            $details[] = $row;
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
