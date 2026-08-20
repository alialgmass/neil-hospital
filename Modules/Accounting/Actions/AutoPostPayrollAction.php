<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\TreasuryService;
use Modules\HR\Models\Payroll;

class AutoPostPayrollAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Post when a payroll record is approved (accrual).
     * Dr 5210 (Salaries) / Cr 2040 (Employee Payables)
     */
    public function onApprove(Payroll $payroll): void
    {
        $amount = (float) $payroll->net_salary;

        if ($amount <= 0) {
            return;
        }

        $salariesId = $this->accountResolver->id(AccountCode::SALARIES);
        $payableId = $this->accountResolver->id(AccountCode::EMPLOYEE_PAYABLE);
        $employeeName = $payroll->employee?->name ?? 'موظف';

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => "استحقاق راتب {$employeeName} — {$payroll->month}/{$payroll->year}",
            'debit_account_id' => $salariesId,
            'credit_account_id' => $payableId,
            'amount' => $amount,
            'source' => JournalSource::SALARY,
            'reference' => (string) $payroll->id,
            'idempotency_key' => "payroll_accrual:{$payroll->id}",
            'cost_center' => CostCenter::Admin,
        ]);
    }

    /**
     * Post when a payroll record is marked paid (settlement).
     * Dr 2040 (Employee Payables) / Cr 1010 (Cash)
     */
    public function onPay(Payroll $payroll): void
    {
        $amount = (float) $payroll->net_salary;

        if ($amount <= 0) {
            return;
        }

        $date = $payroll->paid_at?->toDateString() ?? now()->toDateString();
        $employeeName = $payroll->employee?->name ?? 'موظف';

        $this->treasuryService->record([
            'type' => TreasuryType::Out,
            'description' => "صرف راتب {$employeeName} — {$payroll->month}/{$payroll->year}",
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::SALARY,
        ]);

        $payableId = $this->accountResolver->id(AccountCode::EMPLOYEE_PAYABLE);
        $cashId = $this->accountResolver->id(AccountCode::CASH);

        $this->journalService->record([
            'date' => $date,
            'description' => "صرف راتب {$employeeName} — {$payroll->month}/{$payroll->year}",
            'debit_account_id' => $payableId,
            'credit_account_id' => $cashId,
            'amount' => $amount,
            'source' => JournalSource::SALARY,
            'reference' => (string) $payroll->id,
            'idempotency_key' => "payroll_payment:{$payroll->id}",
            'cost_center' => CostCenter::Admin,
        ]);
    }
}
