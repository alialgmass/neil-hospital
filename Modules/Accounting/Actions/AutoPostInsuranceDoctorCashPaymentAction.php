<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Models\InsuranceClaim;

/**
 * Insurance doctor fees: a fixed amount per case, expensed to 5130 and
 * accrued to the doctor's own payable sub-ledger (22xx, under 2010). The
 * cash leaves the till only once, when the doctor is actually paid
 * (AutoPostDoctorPaymentAction: Dr 22xx / Cr 1010). Crediting 1010 here
 * made the same fee leave the till twice.
 */
class AutoPostInsuranceDoctorCashPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Dr 5130 (Insurance Doctor Fees) / Cr the doctor's payable sub-ledger (22xx)
     *
     * $reference is the booking's file_no. Guide §2.3: the doctor's cash fee
     * (1ج) is one of three same-day entries of an insurance case, so it is
     * refused unless the case's claim revenue (1أ: Dr 1031–1047 / Cr
     * 4110–4150) is already recognized — 5130 can never move on its own.
     *
     * @throws AccountingException
     */
    public function execute(
        float $amount,
        string $doctorId,
        string $doctorName,
        string $reference,
        ?string $date = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if ($idempotencyKey && JournalEntry::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        if (! $this->hasRecognizedInsuranceRevenue($reference)) {
            throw new AccountingException("لا يمكن صرف أتعاب طبيب تأمين (5130) للحالة {$reference} قبل إثبات إيراد مطالبة التأمين (4110–4150) لنفس الحالة.");
        }

        $expenseId = $this->accountResolver->id(AccountCode::INSURANCE_DOCTOR_FEES);
        $payableId = $this->subledgers->forDoctor($doctorId);

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => JournalNarration::make('استحقاق أتعاب طبيب تأمين', [
                'الطبيب' => $doctorName,
                'ملف' => $reference,
                'المبلغ' => JournalNarration::money($amount),
                'التاريخ' => $date ?? now()->toDateString(),
            ]),
            'debit_account_id' => $expenseId,
            'credit_account_id' => $payableId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_DOCTOR_PAYMENT,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'cost_center' => CostCenter::Insurance,
        ]);
    }

    private function hasRecognizedInsuranceRevenue(string $fileNo): bool
    {
        $bookingId = Booking::where('file_no', $fileNo)->value('id');

        if (! $bookingId) {
            return false;
        }

        $revenueIds = Account::whereIn('code', AccountCode::insuranceRevenueCodes())->pluck('id');

        return InsuranceClaim::where('booking_id', $bookingId)->pluck('id')
            ->contains(fn (string $claimId) => JournalEntry::where('idempotency_key', 'like', "insurance_claim_submit:{$claimId}%")
                ->whereIn('credit_account_id', $revenueIds)
                ->whereNull('reversed_at')
                ->whereNull('reversal_of_id')
                ->exists());
    }

    /**
     * Reverse every live (unreversed) insurance-doctor-cash entry for the
     * given reference — used when a booking/claim carrying one is voided or
     * fully rejected.
     */
    public function reverseFor(string $reference, ?string $date = null): void
    {
        JournalEntry::where('reference', $reference)
            ->where('source', JournalSource::INSURANCE_DOCTOR_PAYMENT->value)
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->journalService->reverse(
                entry: $entry,
                reversalSource: JournalSource::REVERSAL,
                reference: 'REV-'.$reference,
                description: JournalNarration::make('عكس استحقاق أتعاب طبيب تأمين', [
                    'ملف' => $reference,
                    'المبلغ' => JournalNarration::money($entry->amount),
                    'البيان الأصلي' => $entry->description,
                ]),
                date: $date,
            ));
    }
}
