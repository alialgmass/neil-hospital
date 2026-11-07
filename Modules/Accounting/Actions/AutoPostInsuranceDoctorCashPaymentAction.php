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
use Modules\Booking\Models\Booking;
use Modules\Insurance\Models\InsuranceClaim;

/**
 * Insurance doctor fees are paid cash, immediately, and never go through the
 * doctor-payable accrual (2010) that every other department uses — a fixed
 * amount per case, debited to 5130 and credited straight to the till.
 */
class AutoPostInsuranceDoctorCashPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Dr 5130 (Insurance Doctor Fees) / Cr 1010 (Cash)
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
        $cashId = $this->accountResolver->id(AccountCode::CASH);

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => JournalNarration::make('أتعاب طبيب تأمين (كاش فوري)', [
                'الطبيب' => $doctorName,
                'ملف' => $reference,
                'المبلغ' => JournalNarration::money($amount),
                'التاريخ' => $date ?? now()->toDateString(),
            ]),
            'debit_account_id' => $expenseId,
            'credit_account_id' => $cashId,
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
                description: JournalNarration::make('عكس أتعاب طبيب تأمين (كاش فوري)', [
                    'ملف' => $reference,
                    'المبلغ' => JournalNarration::money($entry->amount),
                    'البيان الأصلي' => $entry->description,
                ]),
                date: $date,
            ));
    }
}
