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
     * Reverse all accounting entries previously posted for a booking.
     * Called when a booking is cancelled. No-ops gracefully if nothing was posted.
     */
    public function execute(Booking $booking): void
    {
        $today = today()->toDateString();
        $reversalRef = 'REV-'.$booking->file_no;
        $note = "عكس قيد — إلغاء حجز: {$booking->file_no} — {$booking->patient_name}";

        // 1. Reverse journal entries posted for this booking
        $entries = JournalEntry::whereIn('source', [
            JournalSource::BOOKING->value,
            JournalSource::AUTO_BOOKING->value,
        ])
            ->where('reference', $booking->file_no)
            ->get();

        foreach ($entries as $entry) {
            $this->journalService->record([
                'date' => $today,
                'description' => $note,
                'debit_account_id' => $entry->credit_account_id, // swapped
                'credit_account_id' => $entry->debit_account_id,  // swapped
                'amount' => (float) $entry->amount,
                'source' => JournalSource::REVERSAL,
                'reference' => $reversalRef,
                'cost_center' => $entry->cost_center,
            ]);
        }

        // 2. Reverse treasury inflows linked to this booking (refund)
        $treasuryEntries = TreasuryEntry::where('booking_id', $booking->id)
            ->where('type', TreasuryType::In->value)
            ->get();

        foreach ($treasuryEntries as $treasury) {
            $this->treasuryService->record([
                'type' => TreasuryType::Out,
                'description' => "رد مبلغ — إلغاء حجز: {$booking->file_no} — {$booking->patient_name}",
                'amount' => (float) $treasury->amount,
                'date' => $today,
                'source' => JournalSource::REVERSAL,
                'booking_id' => $booking->id,
            ]);
        }
    }
}
