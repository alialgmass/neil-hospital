<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\Doctor\Models\DoctorPayment;

class AutoPostDoctorPaymentAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
    ) {}

    /**
     * Post when doctor dues are paid out.
     * Dr 2010 (Doctor Payables) / Cr 1010 (Cash)
     */
    public function execute(DoctorPayment $payment, string $doctorName): void
    {
        $amount = (float) $payment->amount;

        if ($amount <= 0) {
            return;
        }

        $date = $payment->paid_at?->toDateString() ?? now()->toDateString();

        // Treasury outflow
        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => "صرف مستحقات د. {$doctorName}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::DOCTOR_PAYMENT,
        ]);

        $payableId = DB::table('accounts')->where('code', '2010')->value('id');
        $cashId = DB::table('accounts')->where('code', '1010')->value('id');

        if (! $payableId || ! $cashId) {
            return;
        }

        $this->journalService->record([
            'date' => $date,
            'description' => "صرف مستحقات د. {$doctorName} — VCH-{$payment->id}",
            'debit_account_id' => $payableId,
            'credit_account_id' => $cashId,
            'amount' => $amount,
            'source' => JournalSource::DOCTOR_PAYMENT,
            'reference' => (string) $payment->id,
            'cost_center' => CostCenter::Doctors,
        ]);
    }
}
