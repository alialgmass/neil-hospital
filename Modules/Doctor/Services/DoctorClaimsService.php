<?php

namespace Modules\Doctor\Services;

use Illuminate\Support\Facades\DB;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\FeeType;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class DoctorClaimsService
{
    /**
     * Calculate doctor's total entitlement for a period.
     * Implements all 5 fee strategies from the hospital specification.
     */
    public function calculateClaims(string $doctorId, ?string $from, ?string $to): array
    {
        $doctor = Doctor::findOrFail($doctorId);

        // If insurance doctor → zero entitlement (paid directly)
        if ($doctor->fee_type === FeeType::Insurance) {
            return $this->buildClaimsResult($doctor, $from, $to, 0, []);
        }

        $bookings = DB::table('bookings')
            ->where('doctor_id', $doctorId)
            ->whereNotIn('status', ['cancelled'])
            ->when($from, fn ($q) => $q->whereDate('visit_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('visit_date', '<=', $to))
            ->get();

        $rows = [];
        $totalDrShare = 0.0;

        foreach ($bookings as $booking) {
            $drShare = $this->computeDrShare($doctor, $booking);
            $totalDrShare += $drShare;

            $row = [
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

            if (in_array($booking->dept, ['surgery', 'lasik'])) {
                $surgery = DB::table('surgeries')
                    ->where('booking_id', $booking->id)
                    ->first(['supplies_used', 'supply_total']);

                $row['supplies'] = $surgery
                    ? (json_decode($surgery->supplies_used, true) ?? [])
                    : [];
                $row['supply_total'] = $surgery ? (float) $surgery->supply_total : 0.0;
            }

            $rows[] = $row;
        }

        return $this->buildClaimsResult($doctor, $from, $to, $totalDrShare, $rows);
    }

    /**
     * Doctor's share of a single payment increment on a booking, using the same
     * 5 fee strategies as calculateClaims(). Fixed/insurance fees (flat "per case"
     * amounts) are attributed to the first payment only; percentage-based and
     * surgery/lasik supply-deduction fees are prorated across each payment.
     */
    public function computeShareForPayment(Doctor $doctor, Booking $booking, float $paymentAmount, bool $isFirstPayment): float
    {
        if ($doctor->fee_type === FeeType::Insurance) {
            return 0.0;
        }

        $dept = $booking->dept->value;
        $deptFee = $doctor->dept_fees[$dept] ?? null;

        if ($deptFee && ! in_array($dept, ['surgery', 'lasik'], true)) {
            return $this->computeFeeEntryShareForPayment($deptFee, $paymentAmount, $isFirstPayment);
        }

        if (in_array($dept, ['surgery', 'lasik'], true)) {
            if ($booking->pay_method === PayMethod::Insurance) {
                return $isFirstPayment ? $this->insuranceSurgeryFixedFee($booking) : 0.0;
            }

            return $this->surgeryShareForPayment($booking, $paymentAmount, $isFirstPayment);
        }

        return match ($doctor->fee_type) {
            FeeType::Percentage => round($paymentAmount * ((float) $doctor->fee_value / 100), 2),
            FeeType::Fixed => $isFirstPayment ? (float) $doctor->fee_value : 0.0,
            default => 0.0,
        };
    }

    private function computeFeeEntryShareForPayment(array $deptFee, float $paymentAmount, bool $isFirstPayment): float
    {
        $feeValue = (float) ($deptFee['fee_value'] ?? 0);

        return match ($deptFee['fee_type'] ?? '') {
            'percentage' => round($paymentAmount * ($feeValue / 100), 2),
            'fixed' => $isFirstPayment ? $feeValue : 0.0,
            default => 0.0,
        };
    }

    /**
     * Surgery/Lasik strategy: supply cost is deducted from the first payment only;
     * subsequent installments go to the doctor in full.
     */
    private function surgeryShareForPayment(Booking $booking, float $paymentAmount, bool $isFirstPayment): float
    {
        if (! $isFirstPayment) {
            return max(0, $paymentAmount);
        }

        $supplyTotal = (float) DB::table('surgeries')
            ->where('booking_id', $booking->id)
            ->value('supply_total') ?? 0.0;

        return max(0, $paymentAmount - $supplyTotal);
    }

    private function insuranceSurgeryFixedFee(Booking $booking): float
    {
        return (float) DB::table('services')
            ->where('name', $booking->service_name)
            ->where('dept', $booking->dept->value)
            ->value('dr_share') ?? 0.0;
    }

    private function computeDrShare(Doctor $doctor, object $booking): float
    {
        $paid = (float) $booking->price;
        $insAmount = (float) $booking->ins_amount;
        $dept = $booking->dept;

        // Per-department fee override takes priority
        $deptFee = $doctor->dept_fees[$dept] ?? null;

        if ($deptFee && ! in_array($dept, ['surgery', 'lasik'])) {
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

    private function buildClaimsResult(Doctor $doctor, ?string $from, ?string $to, float $total, array $rows): array
    {
        $paymentRecords = DoctorPayment::where('doctor_id', $doctor->id)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->orderBy('paid_at')
            ->get(['id', 'amount', 'paid_at', 'method', 'notes']);

        $alreadyPaid = (float) $paymentRecords->sum('amount');

        return [
            'doctor' => ['id' => $doctor->id, 'name' => $doctor->name, 'fee_type' => $doctor->fee_type->value],
            'period_from' => $from,
            'period_to' => $to,
            'total_claims' => $total,
            'paid_amount' => $alreadyPaid,
            'net_due' => max(0, $total - $alreadyPaid),
            'rows' => $rows,
            'payments' => $paymentRecords->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at->toDateString(),
                'method' => $p->method,
                'notes' => $p->notes,
            ])->values()->toArray(),
        ];
    }

    public function recordPayment(array $data): DoctorPayment
    {
        return DoctorPayment::create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }

    public function summarizeAll(?string $from, ?string $to): array
    {
        return Doctor::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Doctor $doctor) => collect($this->calculateClaims($doctor->id, $from, $to))
                ->only(['doctor', 'total_claims', 'paid_amount', 'net_due'])
                ->toArray())
            ->values()
            ->toArray();
    }

    public function doctors()
    {
        return Doctor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'fee_type', 'fee_value']);
    }
}
