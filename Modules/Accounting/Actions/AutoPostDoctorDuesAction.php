<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;

class AutoPostDoctorDuesAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Record doctor dues accrual for a shift or booking.
     * Dr 5110 (Clinic/Labs/Laser) or 5120 (Surgery/Lasik) / Cr the doctor's
     * own payable sub-ledger (2201–2299, under the 2010 control account).
     * Tagged with the service's cost center, never the payable's (guide §2.5).
     */
    public function execute(
        Department $dept,
        float $amount,
        string $doctorId,
        string $doctorName,
        string $reference,
        ?string $date = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($amount <= 0 || $dept === Department::Pentacam) {
            return;
        }

        $expenseId = $this->accountResolver->id(AccountCode::doctorExpenseCode($dept));
        $payableId = $this->subledgers->forDoctor($doctorId);

        $this->journalService->record([
            'date' => $date ?? now()->toDateString(),
            'description' => JournalNarration::make('استحقاق نصيب طبيب', [
                'الطبيب' => $doctorName,
                'القسم' => $dept,
                'المبلغ' => JournalNarration::money($amount),
                'المرجع' => $reference,
                'التاريخ' => $date ?? now()->toDateString(),
            ]),
            'debit_account_id' => $expenseId,
            'credit_account_id' => $payableId,
            'amount' => $amount,
            'source' => JournalSource::DOCTOR_SHIFT,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'cost_center' => CostCenter::forDepartment($dept),
        ]);
    }
}
