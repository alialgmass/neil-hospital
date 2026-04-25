<?php

namespace Modules\Doctor\Services;

use Illuminate\Support\Facades\DB;
use Modules\Doctor\Enums\FeeType;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class DoctorClaimsService
{
    /**
     * Calculate doctor's total entitlement for a period.
     * Implements all 5 fee strategies from the hospital specification.
     */
    public function calculateClaims(string $doctorId, string $from, string $to): array
    {
        $doctor = Doctor::findOrFail($doctorId);

        // If insurance doctor → zero entitlement (paid directly)
        if ($doctor->fee_type === FeeType::Insurance) {
            return $this->buildClaimsResult($doctor, $from, $to, 0, []);
        }

        $bookings = DB::table('bookings')
            ->where('doctor_id', $doctorId)
            ->where('pay_status', 'paid')
            ->whereBetween('visit_date', [$from, $to])
            ->get();

        $rows = [];
        $totalDrShare = 0.0;

        foreach ($bookings as $booking) {
            $drShare = $this->computeDrShare($doctor, $booking);
            $totalDrShare += $drShare;

            $rows[] = [
                'booking_id' => $booking->id,
                'file_no' => $booking->file_no,
                'patient_name' => $booking->patient_name,
                'date' => $booking->visit_date,
                'dept' => $booking->dept,
                'service' => $booking->service_name,
                'paid' => (float) $booking->paid_amount,
                'ins_amount' => (float) $booking->ins_amount,
                'dr_share' => $drShare,
            ];
        }

        return $this->buildClaimsResult($doctor, $from, $to, $totalDrShare, $rows);
    }

    private function computeDrShare(Doctor $doctor, object $booking): float
    {
        $paid = (float) $booking->price;
        $insAmount = (float) $booking->ins_amount;
        $dept = $booking->dept;

        // Per-department fee override takes priority
        $deptFee = $doctor->dept_fees[$dept] ?? null;
        if ($deptFee) {
            return $this->computeFromFeeEntry($deptFee, $paid);
        }

        return match (true) {
            // Surgery/Lasik: dr_share = paid − supply_total
            in_array($dept, ['surgery', 'lasik']) => $this->computeSurgeryShare($booking->id, $paid),

            // Insurance surgery: dr_share = fixed fee from service definition (stored as center_val)
            $booking->pay_method === 'insurance' && in_array($dept, ['surgery', 'lasik']) => $this->computeInsuranceSurgeryShare($booking),

            // Clinic, Labs, Laser: dr_share = paid − center_share (from service definition)
            default => $this->computeServiceShare($doctor, $paid, $insAmount),
        };
    }

    private function computeFromFeeEntry(array $deptFee, float $paid): float
    {
        $feeValue = (float) ($deptFee['fee_value'] ?? 0);

        return match ($deptFee['fee_type'] ?? '') {
            'percentage' => round($paid * ($feeValue / 100), 2),
            'fixed' => $feeValue,
            default => 0.0,
        };
    }

    /**
     * Surgery/Lasik strategy: doctor gets paid amount minus supplies cost.
     */
    private function computeSurgeryShare(string $bookingId, float $paid): float
    {
        $supplyTotal = (float) DB::table('surgeries')
            ->where('booking_id', $bookingId)
            ->value('supply_total') ?? 0.0;

        return max(0, $paid - $supplyTotal);
    }

    /**
     * Insurance surgery strategy: triple formula.
     * Center = total − supplies − doctor fee
     * Doctor fee = fixed amount from service definition.
     */
    private function computeInsuranceSurgeryShare(object $booking): float
    {
        $total = (float) $booking->paid_amount + (float) $booking->ins_amount;
        $supplyTotal = (float) DB::table('surgeries')
            ->where('booking_id', $booking->id)
            ->value('supply_total') ?? 0.0;

        $drFixedFee = (float) DB::table('services')
            ->where('name', $booking->service_name)
            ->where('dept', $booking->dept)
            ->value('dr_share') ?? 0.0;

        return $drFixedFee;
    }

    /**
     * Clinic/Labs/Laser strategy: dr_share = paid − center_share.
     * center_share derived from service definition (pct or fixed).
     */
    private function computeServiceShare(Doctor $doctor, float $paid, float $insAmount): float
    {
        if ($doctor->fee_type === FeeType::Percentage) {
            return round($paid * ($doctor->fee_value / 100), 2);
        }

        // Fixed per-case fee
        return (float) $doctor->fee_value;
    }

    private function buildClaimsResult(Doctor $doctor, string $from, string $to, float $total, array $rows): array
    {
        $alreadyPaid = (float) DoctorPayment::where('doctor_id', $doctor->id)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        return [
            'doctor' => ['id' => $doctor->id, 'name' => $doctor->name, 'fee_type' => $doctor->fee_type->value],
            'period_from' => $from,
            'period_to' => $to,
            'total_claims' => $total,
            'paid_amount' => $alreadyPaid,
            'net_due' => max(0, $total - $alreadyPaid),
            'rows' => $rows,
        ];
    }

    public function recordPayment(array $data): DoctorPayment
    {
        return DoctorPayment::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }

    public function doctors()
    {
        return Doctor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'fee_type', 'fee_value']);
    }
}
