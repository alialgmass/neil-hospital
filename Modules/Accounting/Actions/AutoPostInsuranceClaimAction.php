<?php

namespace Modules\Accounting\Actions;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Collection;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\InsuranceReceivableAccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\PaidState;

class AutoPostInsuranceClaimAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly InsuranceReceivableAccountResolver $receivableResolver,
    ) {}

    /**
     * Guide §2.3 entry 1أ — recognize the claim on the SERVICE day, when the
     * claim is raised (accrual basis for insurance), not when its
     * administrative status later moves to "submitted":
     *   Dr {company's 1031–1047 receivable} / Cr {department's 4110–4150 revenue}
     *
     * Idempotent and self-correcting: while the claim's amount / company /
     * department still match the live recognition this is a no-op; if they
     * changed (a draft claim edited from the booking) the old recognition is
     * reversed at its original amount and a fresh one is posted.
     */
    public function recognize(InsuranceClaim $claim): void
    {
        $amount = round((float) $claim->insurance_share, 2);
        $live = $this->liveRecognitions($claim);

        $receivableId = $amount > 0 ? $this->receivableResolver->resolve($claim->insurance_company_id) : null;
        $revenueId = $amount > 0 ? $this->accountResolver->id(AccountCode::insuranceRevenueCode($this->resolveDept($claim))) : null;

        $current = $live->first();

        if ($live->count() === 1 && $amount > 0
            && round((float) $current->amount, 2) === $amount
            && $current->debit_account_id === $receivableId
            && $current->credit_account_id === $revenueId) {
            return;
        }

        $this->reverseRecognition($claim, 'إعادة إثبات مطالبة تأمين بعد تعديلها');

        if ($amount <= 0) {
            return;
        }

        $attempt = JournalEntry::where('idempotency_key', 'like', "insurance_claim_submit:{$claim->id}%")->count();

        $this->journalService->record([
            'date' => $claim->service_date?->toDateString() ?? $claim->claim_date?->toDateString() ?? now()->toDateString(),
            'description' => "مطالبة تأمين: {$claim->file_no} — {$claim->patient_name}",
            'debit_account_id' => $receivableId,
            'credit_account_id' => $revenueId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_CLAIM,
            'reference' => $claim->claim_reference ?? $claim->file_no,
            'idempotency_key' => $attempt === 0 ? "insurance_claim_submit:{$claim->id}" : "insurance_claim_submit:{$claim->id}:{$attempt}",
            'cost_center' => CostCenter::Insurance,
        ]);
    }

    /**
     * Status → submitted. Recognition already happened on the service day;
     * this only guarantees it exists (claims raised before recognition moved
     * to the service day).
     */
    public function onSubmit(InsuranceClaim $claim): void
    {
        $this->recognize($claim);
    }

    /** Whether the claim's revenue (4110–4150) is currently recognized. */
    public function isRecognized(InsuranceClaim $claim): bool
    {
        return $this->liveRecognitions($claim)->isNotEmpty();
    }

    /**
     * Reverse every live recognition of the claim at its original amount —
     * a rejected or deleted claim must not leave revenue and receivable
     * overstated.
     */
    public function reverseRecognition(InsuranceClaim $claim, string $reason): void
    {
        foreach ($this->liveRecognitions($claim) as $entry) {
            $this->journalService->reverse(
                entry: $entry,
                reversalSource: JournalSource::REVERSAL,
                reference: 'REV-'.($claim->claim_reference ?? $claim->file_no),
                description: "عكس قيد — {$reason}: {$claim->file_no} — {$claim->patient_name}",
            );
        }
    }

    /**
     * The booking behind the claim(s) was cancelled or deleted: no service,
     * no insurance revenue. A claim already collected is left alone.
     */
    public function reverseForBooking(string $bookingId, string $reason): void
    {
        InsuranceClaim::where('booking_id', $bookingId)->get()
            ->reject(fn (InsuranceClaim $claim) => $claim->status instanceof PaidState)
            ->each(fn (InsuranceClaim $claim) => $this->reverseRecognition($claim, $reason));
    }

    /** @return Collection<int, JournalEntry> */
    private function liveRecognitions(InsuranceClaim $claim): Collection
    {
        return JournalEntry::where('idempotency_key', 'like', "insurance_claim_submit:{$claim->id}%")
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Post journal entries when an insurance claim is collected.
     *
     * Basis is the APPROVED amount (falls back to the full insurance_share
     * when no partial approval was recorded), never the raw paid_amount:
     *   Dr 1020 (Bank)                     [approved − withholding]
     *   Dr 1080 (Withholding Tax Prepaid)  [approved × company withholding_pct] — an ASSET, not an expense
     *   Dr 5300 (Bad Debt)                 [claim amount − approved]           — only if partially approved
     *   Cr {company's 1031–1047 receivable} — full original claim amount, split across the legs above
     */
    public function onCollect(InsuranceClaim $claim): void
    {
        // The receivable being closed must have been opened (legacy claims
        // collected before service-day recognition existed).
        $this->recognize($claim);

        $receivableId = $this->receivableResolver->resolve($claim->insurance_company_id);
        $claimAmount = (float) $claim->insurance_share;
        $approvedAmount = $claim->approved_amount !== null ? (float) $claim->approved_amount : $claimAmount;

        $withholdingPct = (float) ($claim->company?->withholding_pct ?? 0);
        $withholdingAmount = round($approvedAmount * $withholdingPct / 100, 2);
        $netBank = round($approvedAmount - $withholdingAmount, 2);

        if ($netBank > 0) {
            $bankId = $this->accountResolver->id(AccountCode::BANK);

            $this->journalService->record([
                'date' => $claim->payment_date?->toDateString() ?? now()->toDateString(),
                'description' => "تحصيل تأمين: {$claim->file_no} — {$claim->patient_name}",
                'debit_account_id' => $bankId,
                'credit_account_id' => $receivableId,
                'amount' => $netBank,
                'source' => JournalSource::INSURANCE_COLLECT,
                'reference' => $claim->claim_reference,
                'idempotency_key' => "insurance_claim_collect:{$claim->id}",
                'cost_center' => CostCenter::Insurance,
            ]);
        }

        if ($withholdingAmount > 0) {
            $withholdingId = $this->accountResolver->id(AccountCode::WITHHOLDING_TAX_PREPAID);

            $this->journalService->record([
                'date' => $claim->payment_date?->toDateString() ?? now()->toDateString(),
                'description' => "ضريبة مخصومة من المنبع: {$claim->file_no} — {$claim->patient_name}",
                'debit_account_id' => $withholdingId,
                'credit_account_id' => $receivableId,
                'amount' => $withholdingAmount,
                'source' => JournalSource::INSURANCE_COLLECT,
                'reference' => $claim->claim_reference,
                'idempotency_key' => "insurance_claim_withholding:{$claim->id}",
                'cost_center' => CostCenter::Insurance,
            ]);
        }

        $shortfall = round($claimAmount - $approvedAmount, 2);

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
     * Full rejection (guide §2.4-أ): reverse the recognition (Dr 41xx / Cr
     * 10xx) at its exact original amount. The supplies entry (1ب) is never
     * reversed — the supplies were really consumed and become a loss — and
     * the doctor's same-day cash fee (1ج) stands unless an agreement makes
     * it contingent on acceptance (no such agreement is recorded).
     */
    public function onReject(InsuranceClaim $claim): void
    {
        $this->reverseRecognition($claim, 'رفض مطالبة تأمين');
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
