<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
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
     * Post a booking payment to the treasury and journal.
     *
     * @param  float|null  $amount  Defaults to booking's paid_amount.
     */
    public function execute(Booking $booking, ?float $amount = null): void
    {
        $amount ??= (float) $booking->paid_amount;

        if ($amount <= 0) {
            return;
        }

        $date = $booking->visit_date->toDateString();

        // 1. Treasury entry (cash movement)
        $this->treasuryService->record([
            'type' => TreasuryType::In,
            'description' => "دفعة حجز: {$booking->file_no} — {$booking->patient_name}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::BOOKING,
            'booking_id' => $booking->id,
        ]);

        // 2. Determine debit account (cash/bank/receivable)
        $debitCode = $this->debitAccountCode($booking);
        $debitAccId = $this->findAccountId($debitCode);

        // 3. Determine credit account (revenue by dept or service-specific)
        $creditAccId = $this->findRevenueAccountId($booking);

        if ($debitAccId && $creditAccId) {
            $this->journalService->record([
                'date' => $date,
                'description' => "إيراد حجز: {$booking->file_no} — {$booking->service_name}",
                'debit_account_id' => $debitAccId,
                'credit_account_id' => $creditAccId,
                'amount' => $amount,
                'source' => JournalSource::BOOKING,
                'reference' => $booking->file_no,
                'cost_center' => $this->costCenter($booking->dept),
            ]);
        }
    }

    private function debitAccountCode(Booking $booking): string
    {
        return match ($booking->pay_method) {
            PayMethod::Card, PayMethod::Transfer => '1020', // Bank
            default => '1010', // Main Cash
        };
    }

    private function findAccountId(string $code): ?string
    {
        return DB::table('accounts')->where('code', $code)->value('id');
    }

    private function findRevenueAccountId(Booking $booking): ?string
    {
        // Service-specific revenue account takes priority
        if ($booking->service_id) {
            $serviceAccountId = DB::table('services')
                ->where('id', $booking->service_id)
                ->value('revenue_account_id');

            if ($serviceAccountId) {
                return $serviceAccountId;
            }
        }

        // Fall back to dept-level account per guide chart
        $deptCodeMap = [
            Department::Clinic->value => '4010',
            Department::Labs->value => '4020',
            Department::Surgery->value => '4030',
            Department::Lasik->value => '4040',
            Department::Laser->value => '4050',
        ];

        return $this->findAccountId($deptCodeMap[$booking->dept->value] ?? '4010');
    }

    private function costCenter(Department $dept): CostCenter
    {
        return match ($dept) {
            Department::Clinic => CostCenter::Clinic,
            Department::Labs => CostCenter::Lab,
            Department::Surgery => CostCenter::Surgery,
            Department::Lasik => CostCenter::Lasik,
            Department::Laser => CostCenter::Laser,
        };
    }
}
