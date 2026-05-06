<?php

namespace Modules\Accounting\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;
use Modules\Insurance\Models\InsuranceClaim;

class AutoPostInsuranceClaimAction
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Post journal entry when an insurance claim is submitted.
     * Dr 1030 (Insurance Receivables) / Cr 4110 (Insurance Revenue)
     */
    public function onSubmit(InsuranceClaim $claim): void
    {
        $amount = (float) $claim->insurance_share;

        if ($amount <= 0) {
            return;
        }

        $receivableId = DB::table('accounts')->where('code', '1030')->value('id');
        $revenueId = DB::table('accounts')->where('code', '4110')->value('id');

        if (! $receivableId || ! $revenueId) {
            return;
        }

        $this->journalService->record([
            'date' => $claim->claim_date?->toDateString() ?? now()->toDateString(),
            'description' => "مطالبة تأمين: {$claim->file_no} — {$claim->patient_name}",
            'debit_account_id' => $receivableId,
            'credit_account_id' => $revenueId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_CLAIM,
            'reference' => $claim->claim_reference,
            'cost_center' => CostCenter::Insurance,
        ]);
    }

    /**
     * Post journal entry when an insurance claim is collected.
     * Dr 1010 (Cash) / Cr 1030 (Insurance Receivables)
     */
    public function onCollect(InsuranceClaim $claim): void
    {
        $amount = (float) ($claim->paid_amount ?: $claim->insurance_share);

        if ($amount <= 0) {
            return;
        }

        $cashId = DB::table('accounts')->where('code', '1010')->value('id');
        $receivableId = DB::table('accounts')->where('code', '1030')->value('id');

        if (! $cashId || ! $receivableId) {
            return;
        }

        $this->journalService->record([
            'date' => $claim->payment_date?->toDateString() ?? now()->toDateString(),
            'description' => "تحصيل تأمين: {$claim->file_no} — {$claim->patient_name}",
            'debit_account_id' => $cashId,
            'credit_account_id' => $receivableId,
            'amount' => $amount,
            'source' => JournalSource::INSURANCE_COLLECT,
            'reference' => $claim->claim_reference,
            'cost_center' => CostCenter::Insurance,
        ]);
    }
}
