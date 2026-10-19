<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalNarration;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Accounting\Services\TreasuryService;
use Modules\HR\Models\Payroll;

class AutoPostPayrollAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly TreasuryService $treasuryService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Post when a payroll record is approved (accrual).
     * Dr 5210 (Salaries) / Cr the employee's net-salary sub-ledger (2401–2499, under 2030)
     */
    public function onApprove(Payroll $payroll): void
    {
        $amount = (float) $payroll->net_salary;

        if ($amount <= 0) {
            return;
        }

        $salariesId = $this->accountResolver->id(AccountCode::SALARIES);
        $payableId = $this->subledgers->forEmployee($payroll->employee_id);
        $employeeName = $payroll->employee?->name ?? 'موظف';

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => $this->narration('استحقاق راتب', $payroll, $employeeName),
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
     * Dr the employee's net-salary sub-ledger (2401–2499) / Cr 1010 (Cash)
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
            'description' => $this->narration('صرف راتب', $payroll, $employeeName),
            'amount' => $amount,
            'date' => $date,
            'source' => JournalSource::SALARY,
        ]);

        $payableId = $this->subledgers->forEmployee($payroll->employee_id);
        $cashId = $this->accountResolver->id(AccountCode::CASH);

        $this->journalService->record([
            'date' => $date,
            'description' => $this->narration('صرف راتب', $payroll, $employeeName),
            'debit_account_id' => $payableId,
            'credit_account_id' => $cashId,
            'amount' => $amount,
            'source' => JournalSource::SALARY,
            'reference' => (string) $payroll->id,
            'idempotency_key' => "payroll_payment:{$payroll->id}",
            'cost_center' => CostCenter::Admin,
        ]);
    }

    private function narration(string $title, Payroll $payroll, string $employeeName): string
    {
        return JournalNarration::make($title, [
            'الموظف' => $employeeName,
            'عن شهر' => "{$payroll->month}/{$payroll->year}",
            'الأساسي' => JournalNarration::money($payroll->base_salary),
            'البدلات' => (float) $payroll->allowances > 0 ? JournalNarration::money($payroll->allowances) : null,
            'الإضافي' => (float) $payroll->overtime_pay > 0 ? JournalNarration::money($payroll->overtime_pay) : null,
            'الخصومات' => (float) $payroll->deductions > 0 ? JournalNarration::money($payroll->deductions) : null,
            'الصافي' => JournalNarration::money($payroll->net_salary),
        ]);
    }
}
