<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;

class AutoPostDoctorDuesAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Record doctor dues accrual for a shift or booking.
     * Dr 5110 (Clinic) or 5120 (Surgery/Lasik) / Cr 2010 (Doctor Payables)
     */
    public function execute(
        Department $dept,
        float $amount,
        string $doctorName,
        string $reference,
        ?string $date = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $expenseId = $this->accountResolver->id(AccountCode::doctorExpenseCode($dept));
        $payableId = $this->accountResolver->id(AccountCode::DOCTOR_PAYABLE);

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => "نصيب الطبيب: {$doctorName} — ".$dept->label(),
            'debit_account_id' => $expenseId,
            'credit_account_id' => $payableId,
            'amount' => $amount,
            'source' => JournalSource::DOCTOR_SHIFT,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'cost_center' => CostCenter::Doctors,
        ]);
    }
}
