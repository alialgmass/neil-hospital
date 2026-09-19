<?php

namespace Modules\Doctor\Actions;

use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Actions\AutoPostInsuranceDoctorCashPaymentAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\EntitlementSource;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;

/**
 * Keeps a booking's doctor entitlement (مستحق الطبيب) — and its matching
 * doctor-payable accrual — in sync with the booking.
 *
 * A pending entitlement exists only while the booking is an insurance/contract
 * deal with a doctor and a resolvable per-service fee. Anything else clears the
 * pending entitlement and reverses its accrual. A settled entitlement is never
 * touched here.
 */
class SyncDoctorEntitlementAction
{
    public function __construct(
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
        private readonly AutoPostInsuranceDoctorCashPaymentAction $autoPostInsuranceDoctorCash,
        private readonly JournalService $journalService,
    ) {}

    public function execute(Booking $booking): void
    {
        $existing = DoctorEntitlement::where('booking_id', $booking->id)->first();

        if ($existing && $existing->status === EntitlementStatus::Settled) {
            session()->flash('warning', 'مستحق الطبيب لهذا الحجز تم تسويته بالفعل ولم يتم تعديله.');

            return;
        }

        $source = EntitlementSource::fromPayMethod($booking->pay_method);
        $doctor = $booking->doctor_id ? Doctor::find($booking->doctor_id) : null;

        if ($source === null || $doctor === null || $booking->service_id === null) {
            $this->clearPending($existing);
            $this->reverseAccrual($booking);

            return;
        }

        $amount = $this->resolveFee($booking, $doctor);

        if ($amount === null || $amount <= 0) {
            $this->clearPending($existing);
            $this->reverseAccrual($booking);
            session()->flash('warning', 'تم حفظ الحجز دون إنشاء مستحق للطبيب — لم يتم تحديد أتعاب الطبيب لهذه الخدمة.');

            return;
        }

        DoctorEntitlement::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'doctor_id' => $booking->doctor_id,
                'service_id' => $booking->service_id,
                'amount' => $amount,
                'source' => $source,
                'status' => EntitlementStatus::Pending,
            ],
        );

        $this->syncAccrual($booking, $doctor, $amount, $source);
    }

    /**
     * Void a booking's pending entitlement outright and reverse its accrual —
     * used when the booking is cancelled. A settled entitlement is left as-is.
     */
    public function voidFor(Booking $booking): void
    {
        $this->clearPending(DoctorEntitlement::where('booking_id', $booking->id)->first());
        $this->reverseAccrual($booking);
    }

    /**
     * The booking is about to be hard-deleted: reverse any accrual, drop
     * non-settled entitlements, and leave a settled one behind (its booking_id
     * is nulled by the FK) as an audit record.
     */
    public function onBookingDeleted(Booking $booking): void
    {
        $this->reverseAccrual($booking);

        DoctorEntitlement::where('booking_id', $booking->id)
            ->where('status', '!=', EntitlementStatus::Settled->value)
            ->delete();
    }

    /**
     * The doctor's fee for this specific service: the per-doctor pivot fee,
     * falling back to the service's default doctor fee. Never the service price.
     */
    private function resolveFee(Booking $booking, Doctor $doctor): ?float
    {
        $pivotFee = $doctor->feeForService($booking->service_id);

        if ($pivotFee !== null) {
            return $pivotFee;
        }

        $default = Service::whereKey($booking->service_id)->value('default_dr_fee');

        return $default !== null ? (float) $default : null;
    }

    private function clearPending(?DoctorEntitlement $existing): void
    {
        if ($existing && $existing->status === EntitlementStatus::Pending) {
            $existing->update(['status' => EntitlementStatus::Void]);
        }
    }

    /**
     * Post the doctor's accrual for the current entitlement amount,
     * reversing any earlier accrual for this booking first. A no-op when the
     * accrual for this exact amount is already live.
     *
     * Insurance bookings never accrue to 2010 (doctor payable) — the fixed
     * fee is paid cash, immediately, via AutoPostInsuranceDoctorCashPaymentAction
     * (Dr 5130 / Cr 1010). Contract bookings keep the existing accrual path
     * (Dr 5110/5120 / Cr 2010) — the spec's cash-immediate rule is specific
     * to insurance companies, not every third-party payer.
     */
    private function syncAccrual(Booking $booking, Doctor $doctor, float $amount, EntitlementSource $source): void
    {
        $key = $this->idempotencyKey($booking, $amount, $source);

        $alreadyPosted = JournalEntry::where('idempotency_key', $key)
            ->whereNull('reversed_at')
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $this->reverseAccrual($booking);

        if ($source === EntitlementSource::Insurance) {
            $this->autoPostInsuranceDoctorCash->execute(
                amount: $amount,
                doctorName: $doctor->name,
                reference: $booking->file_no,
                date: $booking->visit_date?->toDateString(),
                idempotencyKey: $key,
            );

            return;
        }

        $this->autoPostDoctorDues->execute(
            dept: $booking->dept,
            amount: $amount,
            doctorName: $doctor->name,
            reference: $booking->file_no,
            date: $booking->visit_date?->toDateString(),
            idempotencyKey: $key,
        );
    }

    private function reverseAccrual(Booking $booking): void
    {
        JournalEntry::where('reference', $booking->file_no)
            ->whereIn('source', [JournalSource::DOCTOR_SHIFT->value, JournalSource::INSURANCE_DOCTOR_PAYMENT->value])
            ->where('idempotency_key', 'like', 'doctor_entitlement:%')
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->journalService->reverse(
                entry: $entry,
                reversalSource: $entry->source === JournalSource::INSURANCE_DOCTOR_PAYMENT ? JournalSource::REVERSAL : JournalSource::DOCTOR_SHIFT,
                reference: $booking->file_no,
                description: "عكس مستحق الطبيب: {$booking->file_no}",
                date: $booking->visit_date?->toDateString(),
            ));
    }

    private function idempotencyKey(Booking $booking, float $amount, EntitlementSource $source): string
    {
        return "doctor_entitlement:{$source->value}:{$booking->id}:".number_format($amount, 2, '.', '');
    }
}
