<?php

namespace Modules\Accounting\Services;

use App\Enums\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\Subledger;

/**
 * The data phases that bring a live ledger onto الدليل المحاسبي v2.0. Each
 * phase is driven by one reversible migration: every change goes through
 * AlignmentLog under the migration's batch name, balances are rebuilt from
 * the journal, and the trial balance must still foot before the phase's
 * transaction commits. Every phase is a no-op on an empty database (fresh
 * installs get the guide chart from AccountsSeeder instead).
 */
class GuideAlignmentService
{
    public function __construct(
        private readonly AlignmentLog $log,
        private readonly SubledgerAccountResolver $subledgers,
        private readonly InsuranceReceivableAccountResolver $receivables,
    ) {}

    /**
     * Phase 1 — chart: insert every guide account that is missing, set every
     * existing one to the guide's exact name / nature / parent / "لا يُرحّل"
     * flag (2010 / 2020 / 2030 become control accounts), then link every
     * doctor, supplier and employee to their sub-ledger account.
     */
    public function alignChart(string $batch): void
    {
        if (! $this->hasLiveChart()) {
            return;
        }

        DB::transaction(function () use ($batch) {
            $codeToId = DB::table('accounts')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [(string) $code => $id])->all();

            foreach (GuideChart::accounts() as $code => $account) {
                $code = (string) $code;
                $parentId = $account['parent'] ? ($codeToId[$account['parent']] ?? null) : null;
                $attributes = [
                    'name' => $account['name'],
                    'group' => $account['group'],
                    'nature' => $account['nature'],
                    'parent_id' => $parentId,
                    'is_active' => 1,
                    'is_postable' => $account['postable'] ? 1 : 0,
                ];

                if (! isset($codeToId[$code])) {
                    $id = (string) Str::ulid();
                    $this->log->insert($batch, 'accounts', [...$attributes, 'id' => $id, 'code' => $code, 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
                    $codeToId[$code] = $id;

                    continue;
                }

                foreach ($attributes as $column => $value) {
                    $this->log->update($batch, 'accounts', $codeToId[$code], $column, $value);
                }
            }

            $this->linkParties($batch);
            $this->assertBalanced('alignChart');
        });
    }

    /**
     * Phase 2 — move every historical line on the control accounts 2010 /
     * 2020 / 2030 to the owning party's sub-ledger, traced from the source
     * document behind the line (booking → doctor, delegation → doctor,
     * doctor payment → doctor, purchase invoice / return / supplier payment
     * → supplier, payroll → employee; a reversal follows its original).
     * Lines with no traceable owner go to the "غير محدّد — للمراجعة" bucket
     * and are flagged `needs_review`.
     */
    public function movePayableHistoryToSubledgers(string $batch): void
    {
        if (! $this->hasLiveChart()) {
            return;
        }

        DB::transaction(function () use ($batch) {
            foreach (Subledger::cases() as $subledger) {
                $masterId = DB::table('accounts')->where('code', $subledger->masterCode()->value)->value('id');

                if (! $masterId) {
                    continue;
                }

                $entries = DB::table('journal_entries')
                    ->where(fn ($q) => $q->where('debit_account_id', $masterId)->orWhere('credit_account_id', $masterId))
                    ->orderBy('created_at')
                    ->get();

                foreach ($entries as $entry) {
                    $partyId = $this->traceOwner($subledger, $entry);
                    $accountId = $partyId ? $this->subledgerAccountFor($batch, $subledger, $partyId) : null;

                    if ($accountId === null) {
                        $accountId = $this->unassignedAccountFor($batch, $subledger);
                        $this->log->update($batch, 'journal_entries', $entry->id, 'needs_review', 1);
                        $this->log->update($batch, 'journal_entries', $entry->id, 'review_note', "لم يُحدَّد صاحب القيد عند ترحيل رصيد {$subledger->masterCode()->value} للحسابات التفصيلية — راجع المستند الأصلي");
                    }

                    foreach (['debit_account_id', 'credit_account_id'] as $side) {
                        if ($entry->{$side} === $masterId) {
                            $this->log->update($batch, 'journal_entries', $entry->id, $side, $accountId);
                        }
                    }
                }
            }

            $this->log->recomputeBalances();
            $this->assertBalanced('movePayableHistoryToSubledgers');
        });
    }

    /**
     * Phase 3 — accounts outside the guide: move their whole history to the
     * guide successor (4065 → 4030 tagged CC-SURG, 4080 → 4210, 5115 → 4070),
     * repoint service revenue overrides, then deactivate them. Any other
     * account not in the guide is deactivated if it never moved; one that
     * moved but has no agreed successor is left active and reported.
     *
     * @return array<int, string> codes that could not be retired
     */
    public function retireOutOfGuideAccounts(string $batch): array
    {
        if (! $this->hasLiveChart()) {
            return [];
        }

        return DB::transaction(function () use ($batch) {
            $guideCodes = GuideChart::codes();
            $blocked = [];

            foreach (DB::table('accounts')->get(['id', 'code']) as $account) {
                $code = (string) $account->code;

                if (in_array($code, $guideCodes, true) || $this->isPartySubledger($code)) {
                    continue;
                }

                $successorCode = GuideChart::RETIRED[$code] ?? null;
                $successorId = $successorCode ? DB::table('accounts')->where('code', $successorCode)->value('id') : null;

                if ($successorId) {
                    $this->moveAccountHistory($batch, $account->id, $successorId, $code === '4065' ? CostCenter::Surgery : null);
                } elseif ($this->hasHistory($account->id)) {
                    $blocked[] = $code;
                    Log::warning("guide_v2_alignment: account {$code} is outside the guide and has history but no agreed successor — left active for manual review.");

                    continue;
                }

                $this->log->update($batch, 'accounts', $account->id, 'is_postable', 0);
                $this->log->update($batch, 'accounts', $account->id, 'is_active', 0);
            }

            $this->log->recomputeBalances();
            $this->assertBalanced('retireOutOfGuideAccounts');

            return $blocked;
        });
    }

    /**
     * Phase 4 — insurance cycle (guide §2.3, entry 1أ): every insurance claim
     * that is not rejected must carry its revenue + receivable recognition
     * (Dr payer 1031–1047 / Cr dept 4110–4150). Claims left in "draft" never
     * got it, which is why 5130 moved while 41xx / 1031–1047 stayed at zero.
     * Missing recognitions are posted on the claim's service date.
     */
    public function recognizeMissingInsuranceRevenue(string $batch): void
    {
        if (! $this->hasLiveChart()) {
            return;
        }

        DB::transaction(function () use ($batch) {
            $claims = DB::table('insurance_claims')
                ->where('status', '!=', 'rejected')
                ->where('insurance_share', '>', 0)
                ->whereNotNull('insurance_company_id')
                ->get();

            foreach ($claims as $claim) {
                $recognized = DB::table('journal_entries')
                    ->where('idempotency_key', 'like', "insurance_claim_submit:{$claim->id}%")
                    ->whereNull('reversed_at')
                    ->whereNull('reversal_of_id')
                    ->exists();

                if ($recognized) {
                    continue;
                }

                $before = DB::table('insurance_companies')->where('id', $claim->insurance_company_id)->value('receivable_account_id');
                $receivableId = $this->receivables->resolve($claim->insurance_company_id);

                if ($before !== $receivableId) {
                    $this->log->recordUpdate($batch, 'insurance_companies', $claim->insurance_company_id, 'receivable_account_id', $before, $receivableId);
                }

                $dept = Department::tryFrom((string) DB::table('bookings')->where('id', $claim->booking_id)->value('dept')) ?? Department::Clinic;
                $revenueId = DB::table('accounts')->where('code', AccountCode::insuranceRevenueCode($dept)->value)->value('id');

                $this->log->insert($batch, 'journal_entries', [
                    'id' => (string) Str::ulid(),
                    'date' => $claim->service_date ?? $claim->claim_date ?? now()->toDateString(),
                    'description' => "إثبات مطالبة تأمين (تسوية الدليل 2.0): {$claim->file_no} — {$claim->patient_name}",
                    'debit_account_id' => $receivableId,
                    'credit_account_id' => $revenueId,
                    'amount' => round((float) $claim->insurance_share, 2),
                    'reference' => $claim->claim_reference ?? $claim->file_no,
                    'idempotency_key' => "insurance_claim_submit:{$claim->id}",
                    'source' => JournalSource::INSURANCE_CLAIM->value,
                    'cost_center' => CostCenter::Insurance->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->flagInsuranceDoctorFeesWithoutRevenue($batch);

            $this->log->recomputeBalances();
            $this->assertBalanced('recognizeMissingInsuranceRevenue');
        });
    }

    /**
     * Phase 5 — inventory relief must be costed to the consuming department's
     * cost account (5010 surgery · 5020 lasik · 5030 medicines · 5040 labs).
     * Historical consumption lines were all costed to 5010 regardless of the
     * department; they are re-pointed by their cost center. A consumption
     * line whose debit is not a cost account at all is flagged for review.
     */
    public function fixInventoryConsumptionCosting(string $batch): void
    {
        if (! $this->hasLiveChart()) {
            return;
        }

        DB::transaction(function () use ($batch) {
            $ids = DB::table('accounts')->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [(string) $code => $id]);
            $inventoryIds = collect(AccountCode::inventoryCodes())->map(fn ($code) => $ids[$code] ?? null)->filter()->values()->all();
            $costOfServiceIds = collect(AccountCode::costOfServiceCodes())->map(fn ($code) => $ids[$code] ?? null)->filter()->values()->all();
            $allowedIds = collect(AccountCode::consumptionCostCodes())->map(fn ($code) => $ids[$code] ?? null)->filter()->values()->all();

            $consumption = DB::table('journal_entries')
                ->whereIn('credit_account_id', $inventoryIds)
                ->where('source', JournalSource::SUPPLIES_USED->value)
                ->whereNull('reversal_of_id')
                ->get();

            foreach ($consumption as $entry) {
                if (! in_array($entry->debit_account_id, $allowedIds, true)) {
                    $this->log->update($batch, 'journal_entries', $entry->id, 'needs_review', 1);
                    $this->log->update($batch, 'journal_entries', $entry->id, 'review_note', 'صرف مخزون غير مُحمَّل على حساب تكلفة — راجع الإذن');

                    continue;
                }

                if (! in_array($entry->debit_account_id, $costOfServiceIds, true)) {
                    continue; // operational supplies (stationery / cleaning / maintenance) stay on their expense account
                }

                $target = $this->costCodeForCostCenter($entry->cost_center, $entry->debit_account_id, $ids->all());
                $targetId = $ids[$target] ?? null;

                if ($targetId && $targetId !== $entry->debit_account_id) {
                    $this->log->update($batch, 'journal_entries', $entry->id, 'debit_account_id', $targetId);

                    DB::table('journal_entries')->where('reversal_of_id', $entry->id)->get(['id'])
                        ->each(fn ($reversal) => $this->log->update($batch, 'journal_entries', $reversal->id, 'credit_account_id', $targetId));
                }
            }

            $this->log->recomputeBalances();
            $this->assertBalanced('fixInventoryConsumptionCosting');
        });
    }

    public function revert(string $batch): void
    {
        DB::transaction(function () use ($batch) {
            $this->log->revert($batch);
            $this->log->recomputeBalances();
            $this->assertBalanced("revert:{$batch}");
        });
    }

    /** A 5130 line whose booking has no insurance revenue recognized cannot be explained — flag it. */
    private function flagInsuranceDoctorFeesWithoutRevenue(string $batch): void
    {
        $feeAccountId = DB::table('accounts')->where('code', AccountCode::INSURANCE_DOCTOR_FEES->value)->value('id');

        if (! $feeAccountId) {
            return;
        }

        $fees = DB::table('journal_entries')
            ->where('debit_account_id', $feeAccountId)
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->get(['id', 'reference']);

        foreach ($fees as $fee) {
            $bookingId = DB::table('bookings')->where('file_no', $fee->reference)->value('id');
            $claimIds = $bookingId ? DB::table('insurance_claims')->where('booking_id', $bookingId)->pluck('id')->all() : [];
            $hasRevenue = collect($claimIds)->contains(fn ($claimId) => DB::table('journal_entries')
                ->where('idempotency_key', 'like', "insurance_claim_submit:{$claimId}%")
                ->whereNull('reversed_at')
                ->exists());

            if (! $hasRevenue) {
                $this->log->update($batch, 'journal_entries', $fee->id, 'needs_review', 1);
                $this->log->update($batch, 'journal_entries', $fee->id, 'review_note', 'أتعاب طبيب تأمين (5130) بدون قيد إيراد تأمين مقابل لنفس الحالة');
            }
        }
    }

    /**
     * @param  array<string, string>  $ids  code => account id
     */
    private function costCodeForCostCenter(?string $costCenter, string $currentId, array $ids): string
    {
        return match ($costCenter) {
            CostCenter::Lasik->value => AccountCode::LASIK_SUPPLIES_COST->value,
            CostCenter::Lab->value, CostCenter::Pentacam->value => AccountCode::LAB_SUPPLIES_COST->value,
            CostCenter::Laser->value, CostCenter::Clinic->value => AccountCode::MEDICINE_COST->value,
            CostCenter::Surgery->value => AccountCode::SURGERY_SUPPLIES_COST->value,
            // CC-INS / store issues: the department is not recoverable from the line — keep its cost account.
            default => (string) (array_search($currentId, $ids, true) ?: AccountCode::SURGERY_SUPPLIES_COST->value),
        };
    }

    private function moveAccountHistory(string $batch, string $fromId, string $toId, ?CostCenter $costCenter): void
    {
        $entries = DB::table('journal_entries')
            ->where(fn ($q) => $q->where('debit_account_id', $fromId)->orWhere('credit_account_id', $fromId))
            ->get();

        foreach ($entries as $entry) {
            foreach (['debit_account_id', 'credit_account_id'] as $side) {
                if ($entry->{$side} === $fromId) {
                    $this->log->update($batch, 'journal_entries', $entry->id, $side, $toId);
                }
            }

            if ($costCenter) {
                $this->log->update($batch, 'journal_entries', $entry->id, 'cost_center', $costCenter->value);
            }
        }

        DB::table('services')->where('revenue_account_id', $fromId)->pluck('id')
            ->each(fn ($serviceId) => $this->log->update($batch, 'services', $serviceId, 'revenue_account_id', $toId));
    }

    private function linkParties(string $batch): void
    {
        foreach (Subledger::cases() as $subledger) {
            DB::table($subledger->table())->whereNull('payable_account_id')->orderBy('created_at')->pluck('id')
                ->each(fn ($partyId) => $this->subledgerAccountFor($batch, $subledger, $partyId));
        }
    }

    /** Resolve (linking / creating on first use) and record what the resolver changed. */
    private function subledgerAccountFor(string $batch, Subledger $subledger, string $partyId): ?string
    {
        $table = $subledger->table();
        $before = DB::table($table)->where('id', $partyId)->value('payable_account_id');

        if ($before) {
            return $before;
        }

        if (! DB::table($table)->where('id', $partyId)->exists()) {
            return null;
        }

        $knownAccountIds = DB::table('accounts')->pluck('id')->all();
        $accountId = $this->subledgers->resolve($subledger, $partyId);

        if (! in_array($accountId, $knownAccountIds, true)) {
            $this->log->recordInsert($batch, 'accounts', $accountId);
        }

        $this->log->recordUpdate($batch, $table, $partyId, 'payable_account_id', null, $accountId);

        return $accountId;
    }

    private function unassignedAccountFor(string $batch, Subledger $subledger): string
    {
        $existing = DB::table('accounts')->where('code', (string) $subledger->unassignedCode())->value('id');

        if ($existing) {
            return $existing;
        }

        $id = $this->subledgers->unassignedAccountId($subledger);
        $this->log->recordInsert($batch, 'accounts', $id);

        return $id;
    }

    /** The party (doctor / supplier / employee id) behind a control-account line, or null. */
    private function traceOwner(Subledger $subledger, object $entry, int $depth = 0): ?string
    {
        if ($entry->reversal_of_id && $depth < 5) {
            $original = DB::table('journal_entries')->where('id', $entry->reversal_of_id)->first();

            if ($original) {
                return $this->traceOwner($subledger, $original, $depth + 1);
            }
        }

        $key = (string) $entry->idempotency_key;
        $reference = (string) $entry->reference;

        return match ($subledger) {
            Subledger::Doctor => $this->traceDoctor($key, $reference, (string) $entry->source),
            Subledger::Supplier => $this->traceSupplier($key, $reference, (string) $entry->source),
            Subledger::Employee => $this->traceEmployee($key, $reference, (string) $entry->source),
        };
    }

    private function traceDoctor(string $key, string $reference, string $source): ?string
    {
        if (preg_match('/^doctor_dues:delegate:([0-9a-z]{26}):/i', $key, $m)
            || preg_match('/^doctor_delegation_entitlement:[^:]+:([0-9a-z]{26})$/i', $key, $m)) {
            return DB::table('booking_doctor_delegations')->where('id', $m[1])->value('doctor_id');
        }

        if (preg_match('/^doctor_entitlement:[^:]+:([0-9a-z]{26}):/i', $key, $m)) {
            return DB::table('bookings')->where('id', $m[1])->value('doctor_id');
        }

        if (preg_match('/^doctor_payment:([0-9a-z]{26})$/i', $key, $m)) {
            return DB::table('dr_payments')->where('id', $m[1])->value('doctor_id');
        }

        if ($source === JournalSource::DOCTOR_PAYMENT->value && $reference !== '') {
            $doctorId = DB::table('dr_payments')->where('id', $reference)->value('doctor_id');

            if ($doctorId) {
                return $doctorId;
            }
        }

        if ($reference !== '') {
            return DB::table('bookings')->where('file_no', $reference)->value('doctor_id');
        }

        return null;
    }

    private function traceSupplier(string $key, string $reference, string $source): ?string
    {
        if (preg_match('/^purchase_(?:invoice|return):([0-9a-z]{26})$/i', $key, $m)) {
            return DB::table('purchase_invoices')->where('id', $m[1])->value('supplier_id');
        }

        if (preg_match('/^supplier_payment:([0-9a-z]{26})$/i', $key, $m)) {
            return DB::table('supplier_payments')->where('id', $m[1])->value('supplier_id');
        }

        if ($reference === '') {
            return null;
        }

        if ($source === JournalSource::SUPPLIER_PAYMENT->value) {
            $supplierId = DB::table('supplier_payments')->where('id', $reference)->value('supplier_id');

            if ($supplierId) {
                return $supplierId;
            }
        }

        $invoiceNo = str_starts_with($reference, 'RET-') ? substr($reference, 4) : $reference;

        return DB::table('purchase_invoices')->where('invoice_no', $invoiceNo)->value('supplier_id');
    }

    private function traceEmployee(string $key, string $reference, string $source): ?string
    {
        if (preg_match('/^payroll_(?:accrual|payment):([0-9a-z]{26})$/i', $key, $m)) {
            return DB::table('payrolls')->where('id', $m[1])->value('employee_id');
        }

        if ($source === JournalSource::SALARY->value && $reference !== '') {
            return DB::table('payrolls')->where('id', $reference)->value('employee_id');
        }

        return null;
    }

    private function isPartySubledger(string $code): bool
    {
        foreach (Subledger::cases() as $subledger) {
            if ((int) $code >= $subledger->firstCode() && (int) $code <= $subledger->unassignedCode()) {
                return true;
            }
        }

        return false;
    }

    private function hasHistory(string $accountId): bool
    {
        return DB::table('journal_entries')
            ->where(fn ($q) => $q->where('debit_account_id', $accountId)->orWhere('credit_account_id', $accountId))
            ->exists();
    }

    /**
     * A seeded installation (one that has the main treasury and the doctors'
     * control account). A bare schema — fresh installs, the test database —
     * only carries the odd account older migrations inserted, and gets the
     * guide chart from AccountsSeeder instead.
     */
    private function hasLiveChart(): bool
    {
        return DB::table('accounts')->whereIn('code', [AccountCode::CASH->value, AccountCode::DOCTOR_PAYABLE->value])->count() === 2;
    }

    private function assertBalanced(string $phase): void
    {
        $this->log->recomputeBalances();
        $this->log->assertTrialBalanceBalanced($phase);
    }
}
