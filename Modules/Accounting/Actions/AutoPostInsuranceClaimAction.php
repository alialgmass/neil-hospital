<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\InsuranceReceivableAccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Insurance\Models\InsuranceClaim;

class AutoPostInsuranceClaimAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly InsuranceReceivableAccountResolver $receivableResolver,
    ) {}

    /**
     * Post journal entry when an insurance claim is submitted.
     * Dr {company's 1031–1047 receivable} / Cr {department's 4110–4150 revenue}
     */
    public function onSubmit(InsuranceClaim $claim): void
    {
        $amount = (float) $claim->insurance_share;

        if ($amount <= 0) {
            return;
        }

        $receivableId = $this->receivableResolver->resolve($claim->insurance_company_id);
        $revenueId = $this->accountResolver->id(AccountCode::insuranceRevenueCode($this->resolveDept($claim)));

        $this->journalService->record([
            'date' => $claim->claim_date?->toDateString() ?? now()->toDateString(),
            'description' => "مطالبة تأمين: {$claim->file_no} — {$claim->patient_name}",
            'debit_account_id' => $receivableId,
            'credit_account_id' => $revenueId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_CLAIM,
            'reference' => $claim->claim_reference,
            'idempotency_key' => "insurance_claim_submit:{$claim->id}",
            'cost_center' => CostCenter::Insurance,
        ]);
    }

    /**
     * Post journal entry when an insurance claim is collected.
     * Dr 1010 (Cash) / Cr {company's 1031–1047 receivable}
     *
     * If the collected amount is less than what was originally booked as
     * receivable (insurance_share) — a partial-approval shortfall — the
     * difference is written off: Dr 5300 (Bad Debt Expense) / Cr receivable.
     */
    public function onCollect(InsuranceClaim $claim): void
    {
        $amount = (float) ($claim->paid_amount ?: $claim->insurance_share);
        $receivableId = $this->receivableResolver->resolve($claim->insurance_company_id);

        if ($amount > 0) {
            $cashId = $this->accountResolver->id(AccountCode::CASH);

            $this->journalService->record([
                'date' => $claim->payment_date?->toDateString() ?? now()->toDateString(),
                'description' => "تحصيل تأمين: {$claim->file_no} — {$claim->patient_name}",
                'debit_account_id' => $cashId,
                'credit_account_id' => $receivableId,
                'amount' => $amount,
                'source' => JournalSource::INSURANCE_COLLECT,
                'reference' => $claim->claim_reference,
                'idempotency_key' => "insurance_claim_collect:{$claim->id}",
                'cost_center' => CostCenter::Insurance,
            ]);
        }

        $shortfall = round((float) $claim->insurance_share - $amount, 2);

        if ($shortfall > 0) {
            $this->writeOffShortfall($claim, $shortfall, $receivableId);
        }
    }

    /**
     * A claim's department, used to route insurance revenue to the correct
     * 4110–4150 account — resolved from the linked booking (falls back to
     * Clinic/4110 if the booking is missing, rather than failing the post).
     */
    private function resolveDept(InsuranceClaim $claim): Department
    {
        return $claim->booking?->dept ?? Department::Clinic;
    }

    /**
     * Reverse the original onSubmit entry (Dr 1030/Cr 4110) using its exact
     * original amount — a rejected claim should not leave the receivable and
     * revenue permanently overstated. No-ops if nothing was submitted, or
     * if it was already reversed.
     */
    public function onReject(InsuranceClaim $claim): void
    {
        $entry = JournalEntry::where('source', JournalSource::INSURANCE_CLAIM->value)
            ->where('reference', $claim->claim_reference)
            ->whereNull('reversed_at')
            ->first();

        if (! $entry) {
            return;
        }

        $this->journalService->reverse(
            entry: $entry,
            reversalSource: JournalSource::REVERSAL,
            reference: 'REV-'.$claim->claim_reference,
            description: "عكس قيد — رفض مطالبة تأمين: {$claim->file_no} — {$claim->patient_name}",
        );
    }

    /**
     * Write off the gap between what was booked as receivable and what was
     * actually collected (partial approval / short payment).
     * Dr 5300 (Bad Debt Expense) / Cr {company's 1031–1047 receivable}
     */
    private function writeOffShortfall(InsuranceClaim $claim, float $shortfall, string $receivableId): void
    {
        $badDebtId = $this->accountResolver->id(AccountCode::BAD_DEBT);

        $this->journalService->record([
            'date' => $claim->payment_date?->toDateString() ?? now()->toDateString(),
            'description' => "إعدام فرق مطالبة تأمين: {$claim->file_no} — {$claim->patient_name}",
            'debit_account_id' => $badDebtId,
            'credit_account_id' => $receivableId,
            'amount' => $shortfall,
            'source' => JournalSource::INSURANCE_COLLECT,
            'reference' => $claim->claim_reference,
            'idempotency_key' => "insurance_claim_writeoff:{$claim->id}",
            'cost_center' => CostCenter::Insurance,
        ]);
    }
}
