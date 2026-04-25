<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;

class AutoPostBookingPaymentAction
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Post a payment for a booking to the treasury and journal.
     *
     * @param  float|null  $amount  Payment amount; defaults to booking's paid_amount.
     */
    public function execute(Booking $booking, ?float $amount = null): void
    {
        $amount ??= (float) $booking->paid_amount;

        if ($amount <= 0) {
            return;
        }

        $date = $booking->visit_date->toDateString();

        // 1. Create treasury entry
        $this->treasuryService->record([
            'type' => TreasuryType::In,
            'description' => "دفعة حجز: {$booking->file_no} — {$booking->patient_name}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::BOOKING,
            'booking_id' => $booking->id,
        ]);

        // 2. Auto-post journal entry: Debit Cash / Credit Revenue
        $cashAccount = $this->findAccount($booking->pay_method === PayMethod::Card ? '1100' : '1000');
        $revenueAccount = $this->findRevenueAccount($booking->dept);

        if ($cashAccount && $revenueAccount) {
            $this->journalService->record([
                'date' => $date,
                'description' => "إيراد حجز: {$booking->file_no} — {$booking->service_name}",
                'debit_account_id' => $cashAccount,
                'credit_account_id' => $revenueAccount,
                'amount' => $amount,
                'source' => JournalSource::BOOKING,
                'reference' => $booking->file_no,
            ]);
        }
    }

    private function findAccount(string $code): ?string
    {
        return DB::table('accounts')->where('code', $code)->value('id');
    }

    private function findRevenueAccount(Department $dept): ?string
    {
        $codeMap = [
            Department::Clinic->value => '2000',
            Department::Labs->value => '2100',
            Department::Surgery->value => '2200',
            Department::Lasik->value => '2300',
            Department::Laser->value => '2400',
        ];

        return $this->findAccount($codeMap[$dept->value] ?? '2000');
    }
}
