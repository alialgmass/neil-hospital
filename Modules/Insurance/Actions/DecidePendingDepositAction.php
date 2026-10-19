<?php

namespace Modules\Insurance\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Enums\DepositDecision;
use Modules\Insurance\Models\PendingDeposit;

class DecidePendingDepositAction
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly AccountResolver $accounts,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Record the company's decision, once. A refusal turns the deposit into
     * private cash revenue (Dr 2050 / Cr the department's revenue account);
     * an approval waits for conversion to a claim.
     *
     * @throws ValidationException
     */
    public function execute(PendingDeposit $deposit, DepositDecision $decision): PendingDeposit
    {
        return DB::transaction(function () use ($deposit, $decision): PendingDeposit {
            $deposit = PendingDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->decision !== DepositDecision::Waiting || $decision === DepositDecision::Waiting) {
                throw ValidationException::withMessages(['deposit' => 'قرار الشركة اتسجّل قبل كده.']);
            }

            $deposit->update(['decision' => $decision]);

            if ($decision === DepositDecision::Rejected) {
                $booking = $deposit->booking;

                $this->journal->record([
                    'date' => now()->toDateString(),
                    'description' => JournalNarration::make('رفض الشركة — الأمانة تحوّلت لإيراد نقدي خاص', [
                        'المريض' => $booking->patient_name,
                        'ملف' => $booking->file_no,
                        'المبلغ' => JournalNarration::money($deposit->amount),
                    ]),
                    'debit_account_id' => $this->accounts->id(AccountCode::PATIENT_ADVANCES),
                    'credit_account_id' => $this->accounts->id(AccountCode::deptRevenueCode($booking->dept)),
                    'amount' => $deposit->amount,
                    'source' => JournalSource::INSURANCE_DEPOSIT,
                    'reference' => $booking->file_no,
                    'idempotency_key' => "insurance_deposit_reclass:{$deposit->id}",
                ]);
            }

            $this->activityLog->log(
                action: 'deposit_decision',
                module: 'insurance_collection',
                recordId: $deposit->id,
                description: "قرار شركة على دفع مؤقت: {$decision->label()}",
            );

            return $deposit;
        });
    }
}
