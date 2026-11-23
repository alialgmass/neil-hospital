<?php

namespace Modules\Doctor\Services;

use Carbon\Carbon;
use Modules\Doctor\Models\Doctor;

/**
 * Thin adapter over {@see DoctorClaimsService}, kept only for its distinct
 * report-shaped return value (doctor_name/period/stats/details). All fee
 * arithmetic — dept_fees overrides, the development-treasury deduction,
 * surgery/lasik supply deduction, insurance entitlements, Pentacam exclusion
 * — lives exclusively in DoctorClaimsService so this and the claims report
 * can never disagree on a doctor's dues.
 */
class ClaimCalculator
{
    public function __construct(
        private readonly DoctorClaimsService $claimsService
    ) {}

    /**
     * Calculate earnings for a doctor in a given period.
     */
    public function calculate(Doctor $doctor, Carbon $from, Carbon $to): array
    {
        $result = $this->claimsService->calculateClaims($doctor->id, $from->toDateString(), $to->toDateString());

        $details = collect($result['rows'])->map(function (array $row): array {
            $detail = [
                'booking_id' => $row['booking_id'],
                'patient' => $row['patient_name'],
                'dept' => $row['dept'],
                'revenue' => $row['paid'],
                'claim' => $row['dr_share'],
                'date' => $row['date'],
            ];

            if (in_array($row['dept'], ['surgery', 'lasik'], true)) {
                $detail['supplies'] = $row['supplies'] ?? [];
                $detail['supply_total'] = $row['supply_total'] ?? 0.0;
            }

            return $detail;
        })->values();

        return [
            'doctor_name' => $doctor->name,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'stats' => [
                'booking_count' => $details->count(),
                'total_revenue' => (float) $details->sum('revenue'),
                'total_claim' => $result['total_claims'],
            ],
            'details' => $details->all(),
        ];
    }
}
