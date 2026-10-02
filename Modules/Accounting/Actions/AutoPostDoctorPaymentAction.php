<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Accounting\Services\TreasuryService;
use Modules\Doctor\Models\DoctorPayment;

class AutoPostDoctorPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Post when doctor dues are paid out.
     * Dr the doctor's payable sub-ledger (2201–2299) / Cr 1010 (Cash) or
     * 1020 (Bank), by payment method.
     */
    public function execute(DoctorPayment $payment, string $doctorName): void
    {
        $amount = (float) $payment->amount;

        if ($amount <= 0) {
            return;
        }

        $date = $payment->paid_at?->toDateString() ?? now()->toDateString();
        $isBank = $payment->method === 'transfer';

        // Treasury outflow (consistent with AutoPostBookingPaymentAction: the
        // treasury log tracks all payment-method movements, not just cash).
        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => "صرف مستحقات د. {$doctorName}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::DOCTOR_PAYMENT,
        ]);

        $payableId = $this->subledgers->forDoctor($payment->doctor_id);
        $creditId = $this->accountResolver->id($isBank ? AccountCode::BANK : AccountCode::CASH);

        $this->journalService->record([
            'date' => $date,
            'description' => "صرف مستحقات د. {$doctorName} — VCH-{$payment->id}",
            'debit_account_id' => $payableId,
            'credit_account_id' => $creditId,
            'amount' => $amount,
            'source' => JournalSource::DOCTOR_PAYMENT,
            'reference' => (string) $payment->id,
            'idempotency_key' => "doctor_payment:{$payment->id}",
            // A payable settlement carries no cost-center dimension of its own
            // (guide §2.5) — it is tracked under the doctors' tracking center.
            'cost_center' => CostCenter::Doctors,
        ]);
    }
}
