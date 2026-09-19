<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;

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

        $expenseId = $this->accountResolver->id(AccountCode::INSURANCE_DOCTOR_FEES);
        $cashId = $this->accountResolver->id(AccountCode::CASH);

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => "أتعاب طبيب تأمين (كاش فوري): {$doctorName}",
            'debit_account_id' => $expenseId,
            'credit_account_id' => $cashId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_DOCTOR_PAYMENT,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'cost_center' => CostCenter::Insurance,
        ]);
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
                description: "عكس أتعاب طبيب تأمين (كاش فوري): {$reference}",
                date: $date,
            ));
    }
}
