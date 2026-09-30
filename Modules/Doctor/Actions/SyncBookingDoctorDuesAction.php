<?php

namespace Modules\Doctor\Actions;

use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\DebtEntryType;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorDebtSettlement;
use Modules\Doctor\Services\DoctorClaimsService;

/**
 * Re-aligns the doctor-dues accrual (Dr 51xx / Cr 2010) posted for a booking's
 * payments with the booking's current price/paid amount — used when a booking
 * is edited after payment.
 *
 * The target is the doctor's share of the whole paid amount, computed exactly
 * like PayBookingController does for payments (as a single first payment),
 * minus any old doctor debt already settled out of this booking's share.
 * When the posted dues differ, they are reversed and the target is reposted.
 */
class SyncBookingDoctorDuesAction
{
    public function __construct(
        private readonly DoctorClaimsService $doctorClaimsService,
        private readonly AutoPostDoctorDuesAction $autoPostDoctorDues,
        private readonly JournalService $journalService,
    ) {}

    public function execute(Booking $booking): void
    {
        $duesEntries = JournalEntry::where('reference', $booking->file_no)
            ->where('source', JournalSource::DOCTOR_SHIFT->value)
            ->where('idempotency_key', 'like', "doctor_dues:{$booking->file_no}:%");

        $liveEntries = (clone $duesEntries)
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->get();

        $doctor = $booking->doctor_id ? Doctor::find($booking->doctor_id) : null;
        $target = $this->targetDues($booking, $doctor);
        $posted = round((float) $liveEntries->sum('amount'), 2);

        if ($target === $posted) {
            return;
        }

        $date = today()->toDateString();

        foreach ($liveEntries as $entry) {
            $this->journalService->reverse(
                entry: $entry,
                reversalSource: JournalSource::DOCTOR_SHIFT,
                reference: $booking->file_no,
                description: "عكس نصيب الطبيب — تعديل سعر حجز: {$booking->file_no}",
                date: $date,
            );
        }

        if ($doctor === null || $target <= 0) {
            return;
        }

        $this->autoPostDoctorDues->execute(
            dept: $booking->dept,
            amount: $target,
            doctorName: $doctor->name,
            reference: $booking->file_no,
            date: $date,
            idempotencyKey: "doctor_dues:{$booking->file_no}:adjust:".(clone $duesEntries)->count().":{$target}",
        );
    }

    private function targetDues(Booking $booking, ?Doctor $doctor): float
    {
        $paid = (float) $booking->paid_amount;

        if ($doctor === null || $paid <= 0) {
            return 0.0;
        }

        $share = $this->doctorClaimsService->computeShareForPayment($doctor, $booking, $paid, true);

        $settledDebt = (float) DoctorDebtSettlement::where('booking_id', $booking->id)
            ->where('doctor_id', $doctor->id)
            ->where('type', DebtEntryType::Settled->value)
            ->sum('amount');

        return max(0.0, round($share - $settledDebt, 2));
    }
}
