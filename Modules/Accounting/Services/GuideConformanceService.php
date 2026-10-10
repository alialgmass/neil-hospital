<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\Subledger;

/**
 * Checks the live ledger against the acceptance criteria of the
 * الدليل المحاسبي v2.0 alignment. Read-only; every check returns a
 * pass/fail with the evidence, so the result can be printed by
 * `accounting:verify-guide` or asserted in tests.
 */
class GuideConformanceService
{
    /**
     * @return array<string, array{label:string, passed:bool, details:array<int, string>}>
     */
    public function run(): array
    {
        return [
            'chart' => $this->chartMatchesGuide(),
            'out_of_guide' => $this->noPostableAccountOutsideGuide(),
            'control_accounts' => $this->controlAccountsHaveNoDirectPostings(),
            'insurance' => $this->insuranceDoctorFeesHaveRevenue(),
            'inventory' => $this->inventoryReliefIsCosted(),
            'trial_balance' => $this->trialBalanceFoots(),
            'review' => $this->linesAwaitingReview(),
        ];
    }

    /**
     * Inventory relief vs recorded cost, split by what relieved it.
     *
     * @return array{relief_total:float, consumption_relief:float, consumption_cost:float, non_consumption_relief:float, by_debit_account:array<string, float>, uncosted:array<int, string>}
     */
    public function inventoryReconciliation(): array
    {
        $codes = DB::table('accounts')->pluck('code', 'id');
        $inventoryIds = $codes->filter(fn ($code) => in_array((string) $code, AccountCode::inventoryCodes(), true))->keys()->all();
        $costCodes = AccountCode::consumptionCostCodes();

        $relief = DB::table('journal_entries')->whereIn('credit_account_id', $inventoryIds)->get(['id', 'debit_account_id', 'amount', 'source', 'reversal_of_id', 'reference']);
        $restock = (float) DB::table('journal_entries')->whereIn('debit_account_id', $inventoryIds)->where('source', JournalSource::REVERSAL->value)
            ->whereIn('credit_account_id', $codes->filter(fn ($code) => in_array((string) $code, $costCodes, true))->keys()->all())
            ->sum('amount');

        $byDebit = [];
        $consumption = 0.0;
        $uncosted = [];

        foreach ($relief as $line) {
            $debitCode = (string) ($codes[$line->debit_account_id] ?? '?');
            $byDebit[$debitCode] = round(($byDebit[$debitCode] ?? 0) + (float) $line->amount, 2);

            if ($line->source === JournalSource::SUPPLIES_USED->value) {
                $consumption += (float) $line->amount;

                if (! in_array($debitCode, $costCodes, true)) {
                    $uncosted[] = "{$line->id} ({$line->reference}) → {$debitCode}";
                }
            }
        }

        $consumptionCost = (float) DB::table('journal_entries')
            ->whereIn('credit_account_id', $inventoryIds)
            ->where('source', JournalSource::SUPPLIES_USED->value)
            ->whereIn('debit_account_id', $codes->filter(fn ($code) => in_array((string) $code, $costCodes, true))->keys()->all())
            ->sum('amount');

        ksort($byDebit);

        return [
            'relief_total' => round((float) $relief->sum('amount'), 2),
            'consumption_relief' => round($consumption - $restock, 2),
            'consumption_cost' => round($consumptionCost - $restock, 2),
            'non_consumption_relief' => round((float) $relief->sum('amount') - $consumption, 2),
            'by_debit_account' => $byDebit,
            'uncosted' => $uncosted,
        ];
    }

    private function chartMatchesGuide(): array
    {
        $details = [];
        $existing = DB::table('accounts as a')->leftJoin('accounts as p', 'p.id', '=', 'a.parent_id')
            ->get(['a.code', 'a.name', 'a.nature', 'a.is_postable', 'a.is_active', 'p.code as parent_code'])
            ->keyBy(fn ($row) => (string) $row->code);

        foreach (GuideChart::accounts() as $code => $account) {
            $row = $existing->get((string) $code);

            if (! $row) {
                $details[] = "{$code} مفقود";

                continue;
            }

            if ($row->name !== $account['name']) {
                $details[] = "{$code} الاسم «{$row->name}» ≠ «{$account['name']}»";
            }

            if ($row->nature !== $account['nature']) {
                $details[] = "{$code} الطبيعة {$row->nature} ≠ {$account['nature']}";
            }

            if ((bool) $row->is_postable !== $account['postable']) {
                $details[] = "{$code} علامة لا يُرحّل غير مطابقة";
            }

            if ((string) $row->parent_code !== (string) $account['parent']) {
                $details[] = "{$code} الحساب الأب {$row->parent_code} ≠ {$account['parent']}";
            }

            if (! $row->is_active) {
                $details[] = "{$code} معطّل";
            }
        }

        return ['label' => 'شجرة الحسابات = الدليل (الأكواد، الأسماء، الطبيعة، لا يُرحّل)', 'passed' => $details === [], 'details' => $details];
    }

    private function noPostableAccountOutsideGuide(): array
    {
        $guideCodes = GuideChart::codes();
        $details = [];

        foreach (DB::table('accounts')->where('is_active', true)->where('is_postable', true)->get(['id', 'code', 'name', 'parent_id']) as $account) {
            if (in_array((string) $account->code, $guideCodes, true) || $this->isSubledgerAccount($account->parent_id)) {
                continue;
            }

            $details[] = "{$account->code} {$account->name}";
        }

        return ['label' => 'لا يوجد حساب خارج الدليل قابل للترحيل', 'passed' => $details === [], 'details' => $details];
    }

    private function controlAccountsHaveNoDirectPostings(): array
    {
        $details = [];

        foreach (Subledger::cases() as $subledger) {
            $master = DB::table('accounts')->where('code', $subledger->masterCode()->value)->first(['id', 'code', 'is_postable']);

            if (! $master) {
                $details[] = "{$subledger->masterCode()->value} مفقود";

                continue;
            }

            $direct = DB::table('journal_entries')->where(fn ($q) => $q->where('debit_account_id', $master->id)->orWhere('credit_account_id', $master->id))->count();

            if ($direct > 0) {
                $details[] = "{$master->code}: {$direct} قيد مُرحّل مباشرة على حساب المراقبة";
            }

            if ($master->is_postable) {
                $details[] = "{$master->code} ما زال قابلًا للترحيل";
            }
        }

        return ['label' => 'حسابات المراقبة 2010/2020/2030 = مجموع تفاصيلها فقط', 'passed' => $details === [], 'details' => $details];
    }

    private function insuranceDoctorFeesHaveRevenue(): array
    {
        $feeId = DB::table('accounts')->where('code', AccountCode::INSURANCE_DOCTOR_FEES->value)->value('id');
        $revenueIds = DB::table('accounts')->whereIn('code', AccountCode::insuranceRevenueCodes())->pluck('id');
        $details = [];

        $fees = $feeId ? DB::table('journal_entries')->where('debit_account_id', $feeId)->whereNull('reversal_of_id')->get(['id', 'reference', 'date']) : collect();

        foreach ($fees as $fee) {
            $bookingId = DB::table('bookings')->where('file_no', $fee->reference)->value('id');
            $claimIds = $bookingId ? DB::table('insurance_claims')->where('booking_id', $bookingId)->pluck('id') : collect();

            $hasRevenue = $claimIds->contains(fn ($claimId) => DB::table('journal_entries')
                ->where('idempotency_key', 'like', "insurance_claim_submit:{$claimId}%")
                ->whereIn('credit_account_id', $revenueIds)
                ->exists());

            if (! $hasRevenue) {
                $details[] = "5130 بدون 41xx: الحالة {$fee->reference} ({$fee->date})";
            }
        }

        return ['label' => 'كل أتعاب طبيب تأمين (5130) لها إيراد تأمين مقابل (4110–4150)', 'passed' => $details === [], 'details' => $details];
    }

    private function inventoryReliefIsCosted(): array
    {
        $reconciliation = $this->inventoryReconciliation();
        $details = $reconciliation['uncosted'];

        if (abs($reconciliation['consumption_relief'] - $reconciliation['consumption_cost']) >= 0.01) {
            $details[] = sprintf('إعفاء المخزون للاستهلاك %.2f ≠ التكلفة المسجّلة %.2f', $reconciliation['consumption_relief'], $reconciliation['consumption_cost']);
        }

        return ['label' => 'إجمالي إعفاء المخزون = إجمالي التكلفة المسجّلة', 'passed' => $details === [], 'details' => $details];
    }

    private function trialBalanceFoots(): array
    {
        $debitSide = 0.0;
        $creditSide = 0.0;
        $details = [];

        $debits = DB::table('journal_entries')->groupBy('debit_account_id')->selectRaw('debit_account_id as id, SUM(amount) as total')->pluck('total', 'id');
        $credits = DB::table('journal_entries')->groupBy('credit_account_id')->selectRaw('credit_account_id as id, SUM(amount) as total')->pluck('total', 'id');

        foreach (DB::table('accounts')->get(['id', 'code', 'nature', 'balance']) as $account) {
            $net = (float) ($debits[$account->id] ?? 0) - (float) ($credits[$account->id] ?? 0);
            $net >= 0 ? $debitSide += $net : $creditSide += -$net;

            $natural = round($account->nature === 'debit' ? $net : -$net, 2);

            if (abs($natural - round((float) $account->balance, 2)) >= 0.01) {
                $details[] = "{$account->code}: الرصيد المخزّن {$account->balance} ≠ رصيد القيود {$natural}";
            }
        }

        if (abs(round($debitSide - $creditSide, 2)) >= 0.01) {
            $details[] = sprintf('مدين %.2f ≠ دائن %.2f', $debitSide, $creditSide);
        }

        return ['label' => sprintf('ميزان المراجعة متوازن (%.2f = %.2f)', $debitSide, $creditSide), 'passed' => $details === [], 'details' => $details];
    }

    private function linesAwaitingReview(): array
    {
        $flagged = DB::table('journal_entries')->where('needs_review', true)->get(['id', 'reference', 'review_note']);

        return [
            'label' => 'قيود مُعلَّمة للمراجعة اليدوية',
            'passed' => $flagged->isEmpty(),
            'details' => $flagged->map(fn ($line) => "{$line->id} ({$line->reference}): {$line->review_note}")->all(),
        ];
    }

    private function isSubledgerAccount(?string $parentId): bool
    {
        if (! $parentId) {
            return false;
        }

        $parentCode = (string) DB::table('accounts')->where('id', $parentId)->value('code');

        return Subledger::forMasterCode($parentCode) !== null;
    }
}
