<?php

namespace Modules\Doctor\Services;

use App\Enums\Department;
use App\Enums\EyeSide;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DebtEntryType;
use Modules\Doctor\Enums\DelegationRole;
use Modules\Doctor\Enums\DelegationStatus;
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
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at')
            ->get();

        // Insurance / contract bookings are settled through a persisted
        // doctor entitlement — take its amount and never re-compute a share
        // for them (that would double-count).
        $entitlements = DB::table('doctor_entitlements')
            ->where('doctor_id', $doctorId)
            ->whereIn('status', ['pending', 'settled'])
            ->pluck('amount', 'booking_id');

        // Per-booking debt already settled out of this doctor's share (see
        // Doctor::settleDebtForBooking()) — subtracted below so a booking
        // whose share was silently diverted to pay down old debt never
        // keeps showing as still "مستحق" (see DoctorDebtSettlement).
        $debtSettled = DB::table('doctor_debt_settlements')
            ->where('doctor_id', $doctorId)
            ->where('type', DebtEntryType::Settled->value)
            ->select('booking_id', DB::raw('SUM(amount) as total'))
            ->groupBy('booking_id')
            ->pluck('total', 'booking_id');

        // Bookings explicitly written off as doctor debt (a 0-payment pay
        // action — see PayBookingController::writeOffAsDoctorDebt()): the
        // whole price became a liability the doctor owes back, so nothing
        // from it is newly payable — never computed a normal share.
        $debtIncurred = DB::table('doctor_debt_settlements')
            ->where('doctor_id', $doctorId)
            ->where('type', DebtEntryType::Incurred->value)
            ->select('booking_id', DB::raw('SUM(amount) as total'))
            ->groupBy('booking_id')
            ->pluck('total', 'booking_id');

        $rows = [];
        $totalDrShare = 0.0;
        $totalDebtDeducted = 0.0;

        foreach ($bookings as $booking) {
            // Only a booking closed out via the 0-payment write-off is
            // "written off" (pay_status Paid with nothing ever collected) —
            // an ordinary still-open Unpaid booking with pending debt (see
            // CreateBookingAction) can still be paid normally later, and a
            // booking that started unpaid but was later paid in full has
            // paid_amount > 0, so both keep computing a real share.
            $writtenOff = $debtIncurred->has($booking->id)
                && $booking->pay_status === 'paid'
                && (float) $booking->paid_amount === 0.0;

            $grossShare = $writtenOff
                ? 0.0
                : ($entitlements->has($booking->id)
                    ? (float) $entitlements[$booking->id]
                    : $this->computeDrShare($doctor, $booking));
            // Debt settled at payment time is deducted from the period total
            // (debt_deducted), never shown against an individual case — the
            // case row always carries its full share.
            $totalDebtDeducted += $writtenOff ? 0.0 : min($grossShare, (float) ($debtSettled[$booking->id] ?? 0));
            $drShare = round($grossShare, 2);
            $totalDrShare += $drShare;

            $delegatedTotal = $this->delegatedTotal($booking->id);
            $delegationLines = $this->delegationBreakdown($booking->id);

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
                'debt_incurred' => $writtenOff ? (float) $debtIncurred[$booking->id] : 0.0,
                // Delegated/anesthesia fees withheld from this booking's own
                // share. The receipt needs this to make its arithmetic add
                // up: dr_share is already net of it, so without the
                // figure the supplies line alone looks like it explains the
                // whole gap between what the patient paid and the gross.
                'delegated_total' => round($delegatedTotal, 2),
                'delegation_lines' => $delegationLines,
            ];

            if (in_array($booking->dept, ['surgery', 'lasik'])) {
                $surgery = DB::table('surgeries')
                    ->where('booking_id', $booking->id)
                    ->first(['supplies_used', 'supply_total']);

                $row['supplies'] = $surgery
                    ? (json_decode($surgery->supplies_used ?? '[]', true) ?? [])
                    : [];
                $row['supply_total'] = $surgery ? (float) $surgery->supply_total : 0.0;
            }

            $rows[] = $row;
        }

        // Delegated / anesthesia dues: this doctor earned these as a second
        // party on someone else's booking (bookings.doctor_id != $doctorId),
        // so they never appear in the loop above. The amount is a fixed,
        // pre-declared fee per line — never recomputed from booking fields.
        $delegationRows = DB::table('booking_doctor_delegations')
            ->join('bookings', 'bookings.id', '=', 'booking_doctor_delegations.booking_id')
            ->where('booking_doctor_delegations.doctor_id', $doctorId)
            ->where('booking_doctor_delegations.status', '!=', DelegationStatus::Void->value)
            ->when($from, fn ($q) => $q->whereDate('bookings.visit_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('bookings.visit_date', '<=', $to))
            ->orderByDesc('bookings.visit_date')
            ->get([
                'booking_doctor_delegations.booking_id',
                'booking_doctor_delegations.role',
                'booking_doctor_delegations.service_name',
                'booking_doctor_delegations.amount',
                'booking_doctor_delegations.status',
                'bookings.file_no',
                'bookings.patient_name',
                'bookings.visit_date',
                'bookings.dept',
            ]);

        foreach ($delegationRows as $delegation) {
            $drShare = round((float) $delegation->amount, 2);
            $totalDebtDeducted += min($drShare, (float) ($debtSettled[$delegation->booking_id] ?? 0));
            $totalDrShare += $drShare;

            $rows[] = [
                'booking_id' => $delegation->booking_id,
                'file_no' => $delegation->file_no,
                'patient_name' => $delegation->patient_name,
                'date' => $delegation->visit_date,
                'dept' => $delegation->dept,
                'service' => $delegation->service_name,
                'paid' => null,
                'ins_amount' => null,
                'dr_share' => $drShare,
                'delegated_total' => 0.0,
                'delegation_lines' => [],
                'role' => $delegation->role,
                'delegation_status' => $delegation->status,
            ];
        }

        return $this->buildClaimsResult($doctor, $from, $to, $totalDrShare, $totalDebtDeducted, $rows);
    }

    /**
     * Sum of delegated/anesthesia doctors' declared fees for this booking —
     * subtracted from the primary doctor's own share so the same fee is
     * never paid twice (see computeDrShare() / computeShareForPayment()).
     */
    public function delegatedTotal(string $bookingId): float
    {
        return (float) DB::table('booking_doctor_delegations')
            ->where('booking_id', $bookingId)
            ->where('status', '!=', DelegationStatus::Void->value)
            ->sum('amount');
    }

    /**
     * The same delegated fees as delegatedTotal(), but broken out per role and
     * per doctor so the case receipt can show what the withheld amount is
     * made of instead of a single anonymous number. Same Void exclusion, so
     * the parts always add up to the total deducted.
     *
     * @return array<int, array{role: string, role_label: string, amount: float, doctor_name: string|null}>
     */
    public function delegationBreakdown(string $bookingId): array
    {
        $rows = DB::table('booking_doctor_delegations')
            ->leftJoin('doctors', 'doctors.id', '=', 'booking_doctor_delegations.doctor_id')
            ->where('booking_doctor_delegations.booking_id', $bookingId)
            ->where('booking_doctor_delegations.status', '!=', DelegationStatus::Void->value)
            ->orderBy('booking_doctor_delegations.id')
            ->get([
                'booking_doctor_delegations.role',
                'booking_doctor_delegations.amount',
                'doctors.name as doctor_name',
            ]);

        return $rows->map(fn (object $row): array => [
            'role' => $row->role instanceof BackedEnum ? $row->role->value : (string) $row->role,
            'role_label' => DelegationRole::tryFrom((string) $row->role)?->label() ?? (string) $row->role,
            'amount' => (float) $row->amount,
            'doctor_name' => $row->doctor_name,
        ])->all();
    }

    /**
     * Supplies actually consumed on this case. Both individually-picked items
     * and بنود (SupplyBundle) are merged into supply_total by
     * SurgeryService::recordSupplies(), so this single figure covers both.
     */
    public function supplyTotalFor(string $bookingId): float
    {
        return (float) (DB::table('surgeries')
            ->where('booking_id', $bookingId)
            ->value('supply_total') ?? 0.0);
    }

    /** The service's own price for the booked side, or its fixed center value. */
    public function centerPriceFor(?string $serviceId, EyeSide|string|null $eyeSide = null): ?float
    {
        return $this->resolveLaserFixedRevenue($serviceId, $eyeSide);
    }

    /** What is still expected to be collected on this booking. */
    private function outstandingFor(Booking $booking): float
    {
        return max(0.0,
            (float) $booking->price
            - (float) $booking->discount
            - (float) $booking->ins_amount
            - (float) $booking->paid_amount
        );
    }

    /**
     * What a zero-payment write-off puts on the doctor — only the cost the
     * booking actually assigns to them, never the whole uncollected price.
     *
     * surgery   — supplies (items + بنود) and the delegated/anesthesia
     *             doctors' declared fees: the exact pair the share
     *             calculation deducts, so a written-off case mirrors a paid
     *             one instead of charging the doctor for the whole price.
     * laser/lasik — the service's own price for the booked side plus the
     *             dev-treasury fee, the same pair their share deducts.
     *
     * Capped at the outstanding amount so a partly-paid booking can never
     * push a doctor past what was expected to be collected. Departments
     * without a rule of their own (clinic/labs) and services with no
     * configured center price keep the legacy "outstanding less dev fee"
     * behaviour rather than silently dropping to no debt at all.
     */
    public function debtForZeroPayment(Booking $booking): float
    {
        $outstanding = $this->outstandingFor($booking);

        if ($outstanding <= 0.0) {
            return 0.0;
        }

        $dept = $booking->dept->value;
        $devFee = $this->resolveDevFee($booking->service_id);
        $legacy = max(0.0, round($outstanding - $devFee, 2));

        // Pentacam never generates a doctor fee (see doComputeDrShare()), so
        // there is nothing for a zero payment to assign to the doctor.
        if ($dept === Department::Pentacam->value) {
            return 0.0;
        }

        if ($dept === Department::Surgery->value) {
            return min($outstanding, $this->supplyTotalFor($booking->id) + $this->delegatedTotal($booking->id));
        }

        if (in_array($dept, [Department::Laser->value, Department::Lasik->value], true)) {
            $centerPrice = $this->centerPriceFor($booking->service_id, $booking->eye_side);

            return $centerPrice !== null
                ? min($outstanding, round($centerPrice + $devFee, 2))
                : $legacy;
        }

        return $legacy;
    }

    /**
     * Doctor's share of a single payment increment on a booking, using the same
     * 5 fee strategies as calculateClaims(). Fixed/insurance fees (flat "per case"
     * amounts) are attributed to the first payment only; percentage-based and
     * surgery/lasik supply-deduction fees are prorated across each payment.
     */
    public function computeShareForPayment(Doctor $doctor, Booking $booking, float $paymentAmount, bool $isFirstPayment): float
    {
        $share = $this->doComputeShareForPayment($doctor, $booking, $paymentAmount, $isFirstPayment);

        if (! $isFirstPayment) {
            return $share;
        }

        // Whatever was delegated to another doctor (see
        // SyncBookingDoctorDelegationsAction) is never paid to the primary
        // doctor too — deducted from the first payment only, mirroring the
        // supply-cost deduction in surgeryShareForPayment().
        return max(0.0, round($share - $this->delegatedTotal($booking->id), 2));
    }

    private function doComputeShareForPayment(Doctor $doctor, Booking $booking, float $paymentAmount, bool $isFirstPayment): float
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

        // Surgery/Lasik/Laser each have their own dedicated fee strategy
        // below (supply-cost deduction, insurance fixed fee, fixed hospital
        // revenue) — a per-department fee override never applies to them.
        // Pentacam is excluded earlier (always 0, see the guard above).
        if ($deptFee && ! in_array($dept, ['surgery', 'lasik', 'laser'], true)) {
            return $this->computeFeeEntryShareForPayment($deptFee, $netAmount, $isFirstPayment);
        }

        // Insurance surgery/lasik: the doctor's own fixed fee, never derived from
        // the service's price/center split. Grouped before the cash branches so
        // lasik cannot slip into the center-price split below and disagree with
        // doComputeDrShare(), which puts insurance lasik on the same fixed fee.
        if ($booking->pay_method === PayMethod::Insurance
            && in_array($dept, [Department::Surgery->value, Department::Lasik->value], true)) {
            return $isFirstPayment ? $this->insuranceSurgeryFixedFee($doctor, $booking) : 0.0;
        }

        if ($dept === Department::Surgery->value) {
            return $this->surgeryShareForPayment($booking, $netAmount, $isFirstPayment);
        }

        // Laser/Lasik strategy (fixed hospital revenue): the doctor gets whatever is
        // left after the hospital's fixed cut for this service, not a
        // percentage/fixed fee of their own. Deducted on the first payment
        // only, mirroring the surgery supply deduction above.
        if (in_array($dept, [Department::Laser->value, Department::Lasik->value], true)) {
            $fixedRevenue = $this->resolveLaserFixedRevenue($booking->service_id, $booking->eye_side);

            if ($fixedRevenue !== null) {
                return $isFirstPayment ? max(0, round($netAmount - $fixedRevenue, 2)) : max(0, $netAmount);
            }
        }

        return match ($doctor->fee_type) {
            FeeType::Percentage => round($netAmount * ((float) $doctor->fee_value / 100), 2),
            FeeType::Fixed => $isFirstPayment ? (float) $doctor->fee_value : 0.0,
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
        $share = $this->doComputeDrShare($doctor, $booking);

        // Whatever was delegated to another doctor (see
        // SyncBookingDoctorDelegationsAction) is never paid to the primary
        // doctor too.
        return max(0.0, round($share - $this->delegatedTotal($booking->id), 2));
    }

    private function doComputeDrShare(Doctor $doctor, object $booking): float
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

        // Laser/Lasik: the patient pays above the service's own price and the
        // doctor keeps whatever is left after the service price + dev fee
        // (see computeShareForPayment()). booking->price is that service
        // price, so the share must come from what was actually collected —
        // otherwise it always nets to 0.
        if (in_array($dept, [Department::Laser->value, Department::Lasik->value], true)) {
            $paid = max($paid, (float) ($booking->paid_amount ?? 0));
        }

        // Development-treasury fee is deducted from the price BEFORE the
        // doctor's share is computed (cash bookings only — see
        // AutoPostDevelopmentFeeAction). Fixed/flat fee amounts are
        // unaffected since they don't derive from $paid.
        $netPaid = $booking->pay_method === 'cash'
            ? max(0, $paid - $this->resolveDevFee($booking->service_id ?? null))
            : $paid;

        // Per-department fee override takes priority — except Surgery/
        // Lasik/Laser, which each have their own dedicated fee strategy
        // below. Pentacam is excluded earlier (always 0, see the guard above).
        $deptFee = $doctor->dept_fees[$dept] ?? null;

        if ($deptFee && ! in_array($dept, ['surgery', 'lasik', 'laser'])) {
            return $this->computeFromFeeEntry($deptFee, $netPaid);
        }

        return match (true) {
            // Insurance surgery/lasik: dr_share = the doctor's fixed fee (Doctors
            // module — see resolveDoctorFixedFee()). Lasik sits here too so this
            // path agrees with computeShareForPayment(), which also keeps
            // insurance lasik on the fee-based strategy rather than letting the
            // center-price branch below swallow it.
            $booking->pay_method === 'insurance' && in_array($dept, ['surgery', 'lasik'], true) => $this->computeInsuranceSurgeryShare($doctor, $booking),

            // Surgery: dr_share = net paid − supply_total
            $dept === Department::Surgery->value => $this->computeSurgeryShare($booking->id, $netPaid),

            // Clinic, Labs, Lasik, Laser: dr_share = f(net paid) per doctor
            // fee_type, except Lasik/Laser which take the eye-priced or
            // fixed-center split when the service is configured that way — see
            // computeServiceShare() and resolveLaserFixedRevenue().
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
     * Clinic/Labs/Lasik/Laser strategy: dr_share = paid − center_share.
     * center_share derived from service definition (pct or fixed).
     *
     * Lasik/Laser are special-cased first, mirroring computeShareForPayment():
     * the doctor gets whatever remains of the (already dev-fee-netted) paid
     * amount after the hospital's cut — the service's one-eye/both-eyes price
     * for the booked side, or its legacy fixed center_val — not a
     * percentage/fixed fee of their own. Lasik follows the laser rule exactly;
     * only a service with neither configured falls through to the doctor's own
     * fee_type strategies.
     */
    private function computeServiceShare(Doctor $doctor, float $paid, float $insAmount, ?string $dept = null, ?string $serviceId = null, EyeSide|string|null $eyeSide = null): float
    {
        if (in_array($dept, [Department::Laser->value, Department::Lasik->value], true)) {
            $fixedRevenue = $this->resolveLaserFixedRevenue($serviceId, $eyeSide);

            if ($fixedRevenue !== null) {
                return max(0, round($paid - $fixedRevenue, 2));
            }
        }

        return match ($doctor->fee_type) {
            FeeType::Percentage => round($paid * ($doctor->fee_value / 100), 2),
            FeeType::Fixed => (float) $doctor->fee_value,
        };
    }

    /**
     * total_claims is the period's dues after the doctor's debt deduction:
     * gross_claims (sum of every case's full share) minus debt_deducted (all
     * debt settled out of this period's cases, applied to the total).
     */
    private function buildClaimsResult(Doctor $doctor, ?string $from, ?string $to, float $grossTotal, float $debtDeducted, array $rows): array
    {
        $grossTotal = round($grossTotal, 2);
        $debtDeducted = round(min($grossTotal, $debtDeducted), 2);
        $total = round($grossTotal - $debtDeducted, 2);

        $paymentRecords = DoctorPayment::where('doctor_id', $doctor->id)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->orderByDesc('paid_at')
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
            'gross_claims' => $grossTotal,
            'debt_deducted' => $debtDeducted,
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
