<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Booking\Models\Booking;

class ReverseBookingPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
    ) {}

    /**
     * Reverse all accounting entries previously posted for a booking:
     * revenue entries AND any accrued doctor dues tied to it. Called when a
     * booking is cancelled. No-ops gracefully if nothing was posted, and
     * skips entries already reversed (idempotent — safe to call twice).
     *
     * Always reverses using the ORIGINAL entry's amount/accounts — never
     * recalculates today's values.
     */
    public function execute(Booking $booking): void
    {
        $today = today()->toDateString();
        $reversalRef = 'REV-'.$booking->file_no;
        $note = "عكس قيد — إلغاء حجز: {$booking->file_no} — {$booking->patient_name}";

        // 1. Reverse revenue + doctor-dues journal entries posted for this booking
        $entries = JournalEntry::whereIn('source', [
            JournalSource::BOOKING->value,
            JournalSource::AUTO_BOOKING->value,
            JournalSource::DOCTOR_SHIFT->value,
        ])
            ->where('reference', $booking->file_no)
            ->whereNull('reversed_at')
            ->get();

        foreach ($entries as $entry) {
            $this->journalService->reverse(
                entry: $entry,
                reversalSource: JournalSource::REVERSAL,
                reference: $reversalRef,
                description: $note,
                date: $today,
            );
        }

        // 2. Reverse treasury inflows linked to this booking (refund) — only
        // for entries not already reversed (idempotent double-cancel guard).
        $alreadyRefunded = (float) TreasuryEntry::where('booking_id', $booking->id)
            ->where('type', TreasuryType::Out->value)
            ->where('source', JournalSource::REVERSAL->value)
            ->sum('amount');

        $treasuryEntries = TreasuryEntry::where('booking_id', $booking->id)
            ->where('type', TreasuryType::In->value)
            ->get();

        $totalIn = (float) $treasuryEntries->sum('amount');
        $toRefund = round($totalIn - $alreadyRefunded, 2);

        if ($toRefund > 0) {
            $this->treasuryService->record([
                'type' => TreasuryType::Out,
                'description' => "رد مبلغ — إلغاء حجز: {$booking->file_no} — {$booking->patient_name}",
                'amount' => $toRefund,
                'date' => $today,
                'source' => JournalSource::REVERSAL,
                'booking_id' => $booking->id,
            ]);
        }
    }
}
