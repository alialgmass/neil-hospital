<?php

namespace Modules\Doctor\Services;

use App\Enums\Department;
use App\Enums\EyeSide;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\FeeType;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
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

        $bookings = DB::table('bookings')
            ->where('doctor_id', $doctorId)
            ->whereNotIn('status', ['cancelled'])
            ->when($from, fn ($q) => $q->whereDate('visit_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('visit_date', '<=', $to))
            ->get();

        // Insurance / contract bookings are settled through a persisted
        // doctor entitlement — take its amount and never re-compute a share
        // for them (that would double-count).
        $entitlements = DB::table('doctor_entitlements')
            ->where('doctor_id', $doctorId)
            ->whereIn('status', ['pending', 'settled'])
            ->pluck('amount', 'booking_id');

        $rows = [];
        $totalDrShare = 0.0;

        foreach ($bookings as $booking) {
            $drShare = $entitlements->has($booking->id)
                ? (float) $entitlements[$booking->id]
                : $this->computeDrShare($doctor, $booking);
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
        // Pentacam never generates a doctor fee, regardless of fee configuration.
        if ($booking->dept === Department::Pentacam) {
            return 0.0;
        }

        // Insurance / contract bookings that carry a persisted entitlement accrue
        // the doctor's share up front through it, not per cash payment. Bookings
        // with no entitlement (e.g. created before this feature, or no fee
        // configured) keep the legacy payment-time accrual unchanged.
        if ($booking->pay_method->isThirdParty()
            && DoctorEntitlement::where('booking_id', $booking->id)->exists()) {
            return 0.0;
        }

        // Development-treasury fee is deducted from the price BEFORE the
        // doctor's share is computed (cash bookings only, first payment
        // only — the fee itself is posted once per booking regardless of
        // installments, see AutoPostDevelopmentFeeAction).
        $netAmount = $this->netPaymentBase($booking, $paymentAmount, $isFirstPayment);

        $dept = $booking->dept->value;
        $deptFee = $doctor->dept_fees[$dept] ?? null;

        if ($deptFee && ! in_array($dept, ['surgery', 'lasik'], true)) {
            return $this->computeFeeEntryShareForPayment($deptFee, $netAmount, $isFirstPayment);
        }

        if (in_array($dept, ['surgery', 'lasik'], true)) {
            if ($booking->pay_method === PayMethod::Insurance) {
                return $isFirstPayment ? $this->insuranceSurgeryFixedFee($doctor, $booking) : 0.0;
            }

            return $this->surgeryShareForPayment($booking, $netAmount, $isFirstPayment);
        }

        // Laser strategy (fixed hospital revenue): the doctor gets whatever is
        // left after the hospital's fixed cut for this service, not a
        // percentage/fixed fee of their own. Deducted on the first payment
        // only, mirroring the surgery/lasik supply deduction above.
        if ($dept === Department::Laser->value) {
            $fixedRevenue = $this->resolveLaserFixedRevenue($booking->service_id, $booking->eye_side);

            if ($fixedRevenue !== null) {
                return $isFirstPayment ? max(0, round($netAmount - $fixedRevenue, 2)) : max(0, $netAmount);
            }
        }

        return match ($doctor->fee_type) {
            FeeType::Percentage => round($netAmount * ((float) $doctor->fee_value / 100), 2),
            FeeType::Fixed => $isFirstPayment ? (float) $doctor->fee_value : 0.0,
            default => 0.0,
        };
    }

    /**
     * Resolve the booking's service dev_treasury_fee and subtract it once
     * from the amount used as the doctor-share calculation base — cash
     * bookings only, and only on the first payment (the fee is a single
     * fixed deduction per booking, not per installment).
     */
    private function netPaymentBase(Booking $booking, float $amount, bool $isFirstPayment): float
    {
        if (! $isFirstPayment || $booking->pay_method !== PayMethod::Cash) {
            return $amount;
        }

        return max(0, $amount - $this->resolveDevFee($booking->service_id));
    }

    private function resolveDevFee(?string $serviceId): float
    {
        if ($serviceId === null) {
            return 0.0;
        }

        return (float) (Service::whereKey($serviceId)->value('dev_treasury_fee') ?? 0);
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
     * subsequent installments go to the doctor in full. $paymentAmount has already
     * had the dev-treasury fee netted out (first payment, cash only) by the caller.
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

    /**
     * The hospital's cut of a Laser service — deducted from the (already
     * dev-fee-netted) paid amount, the doctor keeps the remainder. Two
     * configurations, checked in order:
     *
     * 1. Eye-priced service, booking has a recorded eye side: the cut is the
     *    service's price for that side (one_eye_price / both_eyes_price) —
     *    the patient is expected to pay above this floor; the doctor's share
     *    is whatever they paid beyond it (0 if they paid exactly the floor).
     *    Gated on the booking actually carrying an eye side so this never
     *    fires for bookings that predate eye tracking — a migration
     *    backfilled one_eye_price = price on every existing service, so
     *    without this gate every legacy fixed-center booking would wrongly
     *    match here too and its doctor share would silently zero out.
     * 2. Legacy fixed-center service (center_type = 'fixed'): the cut is the
     *    flat center_val, regardless of eye side.
     *
     * Returns null when neither applies, so callers fall back to the
     * doctor's own fee_type/fee_value.
     */
    private function resolveLaserFixedRevenue(?string $serviceId, EyeSide|string|null $eyeSide = null): ?float
    {
        if ($serviceId === null) {
            return null;
        }

        $service = DB::table('services')->where('id', $serviceId)
            ->first(['center_type', 'center_val', 'one_eye_price', 'both_eyes_price']);

        if (! $service) {
            return null;
        }

        if ($eyeSide !== null) {
            $eyeSideValue = $eyeSide instanceof EyeSide ? $eyeSide->value : $eyeSide;
            $eyePrice = $eyeSideValue === EyeSide::OU->value ? $service->both_eyes_price : $service->one_eye_price;

            if ($eyePrice !== null) {
                return (float) $eyePrice;
            }
        }

        if ($service->center_type !== 'fixed') {
            return null;
        }

        return (float) $service->center_val;
    }

    /**
     * The doctor's fixed fee for an insurance surgery/lasik booking: their
     * own per-service rate (doctor_service.fee), falling back to the
     * service's default_dr_fee. Never derived from the service's
     * price/center split — see resolveDoctorFixedFee() and
     * SyncDoctorEntitlementAction::resolveFee(), the same rule applied
     * to the newer entitlement-based accrual path.
     */
    private function insuranceSurgeryFixedFee(Doctor $doctor, Booking $booking): float
    {
        return $this->resolveDoctorFixedFee($doctor, $booking->service_id);
    }

    private function resolveDoctorFixedFee(Doctor $doctor, ?string $serviceId): float
    {
        if ($serviceId === null) {
            return 0.0;
        }

        $pivotFee = $doctor->feeForService($serviceId);

        if ($pivotFee !== null) {
            return $pivotFee;
        }

        return (float) (Service::whereKey($serviceId)->value('default_dr_fee') ?? 0);
    }

    public function computeDrShare(Doctor $doctor, object $booking): float
    {
        $dept = $booking->dept;

        // Pentacam never generates a doctor fee, regardless of fee configuration.
        if ($dept === Department::Pentacam->value) {
            return 0.0;
        }

        // booking->price is the authoritative base, not the service's list
        // price: a patient discount must come out of the doctor's own share,
        // never out of the hospital's fixed cut (see LaserFixedHospitalRevenueTest).
        $paid = (float) $booking->price;
        $insAmount = (float) $booking->ins_amount;

        // Development-treasury fee is deducted from the price BEFORE the
        // doctor's share is computed (cash bookings only — see
        // AutoPostDevelopmentFeeAction). Fixed/flat fee amounts are
        // unaffected since they don't derive from $paid.
        $netPaid = $booking->pay_method === 'cash'
            ? max(0, $paid - $this->resolveDevFee($booking->service_id ?? null))
            : $paid;

        // Per-department fee override takes priority
        $deptFee = $doctor->dept_fees[$dept] ?? null;

        if ($deptFee && ! in_array($dept, ['surgery', 'lasik'])) {
            return $this->computeFromFeeEntry($deptFee, $netPaid);
        }

        return match (true) {
            // Insurance surgery: dr_share = the doctor's fixed fee (Doctors module — see resolveDoctorFixedFee())
            $booking->pay_method === 'insurance' && in_array($dept, ['surgery', 'lasik']) => $this->computeInsuranceSurgeryShare($doctor, $booking),

            // Surgery/Lasik: dr_share = net paid − supply_total
            in_array($dept, ['surgery', 'lasik']) => $this->computeSurgeryShare($booking->id, $netPaid),

            // Clinic, Labs, Laser: dr_share = f(net paid) per doctor fee_type
            // (Laser falls back to an eye-priced or fixed-hospital-revenue
            // split when the service is configured that way — see
            // resolveLaserFixedRevenue()).
            default => $this->computeServiceShare($doctor, $netPaid, $insAmount, $dept, $booking->service_id ?? null, $booking->eye_side ?? null),
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
     * Insurance surgery strategy: dr_share = the doctor's fixed fee — their
     * own per-service rate (Doctors module), never derived from the
     * service's price/center split. See resolveDoctorFixedFee().
     */
    private function computeInsuranceSurgeryShare(Doctor $doctor, object $booking): float
    {
        return $this->resolveDoctorFixedFee($doctor, $booking->service_id ?? null);
    }

    /**
     * Clinic/Labs/Laser strategy: dr_share = paid − center_share.
     * center_share derived from service definition (pct or fixed).
     *
     * Laser is special-cased first, mirroring computeShareForPayment(): the
     * doctor gets whatever remains of the (already dev-fee-netted) paid
     * amount after the hospital's cut — the service's one-eye/both-eyes
     * price for the booked side, or its legacy fixed center_val — not a
     * percentage/fixed fee of their own. Falls through to the normal doctor
     * fee_type strategies when the service has neither configured.
     */
    private function computeServiceShare(Doctor $doctor, float $paid, float $insAmount, ?string $dept = null, ?string $serviceId = null, EyeSide|string|null $eyeSide = null): float
    {
        if ($dept === Department::Laser->value) {
            $fixedRevenue = $this->resolveLaserFixedRevenue($serviceId, $eyeSide);

            if ($fixedRevenue !== null) {
                return max(0, round($paid - $fixedRevenue, 2));
            }
        }

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
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'fee_type' => $doctor->fee_type->value,
                'debt_balance' => (float) $doctor->doctor_debt_balance,
            ],
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
