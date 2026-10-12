<?php

namespace Modules\Insurance\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Admin\Services\ActivityLogService;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Models\PendingDeposit;

class RecordPendingDepositAction
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * The patient pays cash up front while the company decides: money held
     * as a liability (Dr cash / Cr 2050 patient advances), not revenue.
     */
    public function execute(Booking $booking, float $amount, int $userId): PendingDeposit
    {
        return DB::transaction(function () use ($booking, $amount, $userId): PendingDeposit {
            $deposit = PendingDeposit::create([
                'booking_id' => $booking->id,
                'insurance_company_id' => $booking->ins_company_id,
                'amount' => $amount,
                'paid_on' => now()->toDateString(),
                'created_by' => $userId,
            ]);

            $this->journal->record([
                'date' => $deposit->paid_on->toDateString(),
                'description' => JournalNarration::make('أمانة مريض تعاقد', [
                    'المريض' => $booking->patient_name,
                    'ملف' => $booking->file_no,
                    'المبلغ' => JournalNarration::money($amount),
                ]),
                'debit_account_id' => $this->accounts->id(AccountCode::CASH),
                'credit_account_id' => $this->accounts->id(AccountCode::PATIENT_ADVANCES),
                'amount' => $amount,
                'source' => JournalSource::INSURANCE_DEPOSIT,
                'reference' => $booking->file_no,
                'idempotency_key' => "insurance_deposit:{$deposit->id}",
            ]);

            $this->activityLog->log(
                action: 'deposit',
                module: 'insurance_collection',
                recordId: $deposit->id,
                description: "تسجيل أمانة {$booking->patient_name} — ".number_format($amount).' ج.م',
            );

            return $deposit;
        });
    }
}
