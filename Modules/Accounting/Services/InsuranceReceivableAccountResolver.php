<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Exceptions\AccountingException;

/**
 * Resolves each insurance company's own receivable sub-account (1031–1047,
 * or a dedicated 1061–1079 one) instead of the shared aggregate 1030
 * (non-postable, reporting roll-up only). A company is lazily assigned its
 * account the first time a claim is posted for it, and keeps it from then on.
 */
class InsuranceReceivableAccountResolver
{
    private const POOL_PARENT_CODE = '1030';

    private const NAME_PREFIX = 'ذمم تأمين — ';

    /** Codes for companies the guide has no named account for. */
    private const DEDICATED_FIRST = 1061;

    private const DEDICATED_LAST = 1079;

    /**
     * Resolve (and persist, on first use) the given insurance company's
     * receivable account id.
     */
    public function resolve(string $insuranceCompanyId): string
    {
        $existing = DB::table('insurance_companies')
            ->where('id', $insuranceCompanyId)
            ->value('receivable_account_id');

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($insuranceCompanyId) {
            $current = DB::table('insurance_companies')
                ->where('id', $insuranceCompanyId)
                ->lockForUpdate()
                ->value('receivable_account_id');

            if ($current) {
                return $current;
            }

            $accountId = $this->nextFreeAccountId($insuranceCompanyId);

            DB::table('insurance_companies')
                ->where('id', $insuranceCompanyId)
                ->update(['receivable_account_id' => $accountId]);

            return $accountId;
        });
    }

    /**
     * The company's own guide sub-account — matched by name, e.g. a company
     * called "نفقة الدولة" gets 1031 "ذمم تأمين — نفقة الدولة" (word order is
     * ignored: "جولد ميد" ≡ "ميد جولد"). A company with no guide account of
     * its own gets a NEW dedicated sub-account (1061–1079) — it must never be
     * handed a free guide account named after another company, which would
     * merge the two companies' receivables.
     */
    private function nextFreeAccountId(string $insuranceCompanyId): string
    {
        $poolParentId = DB::table('accounts')->where('code', self::POOL_PARENT_CODE)->value('id');

        $usedIds = DB::table('insurance_companies')
            ->whereNotNull('receivable_account_id')
            ->pluck('receivable_account_id');

        $free = DB::table('accounts')
            ->where('parent_id', $poolParentId)
            ->whereNotIn('id', $usedIds->all() ?: ['__none__'])
            ->orderBy('code')
            ->get(['id', 'name']);

        $companyName = (string) DB::table('insurance_companies')->where('id', $insuranceCompanyId)->value('name');

        $match = $free->first(fn ($account) => $this->namesMatch($companyName, $this->accountCompanyName($account->name)));

        return $match->id ?? $this->createDedicatedAccount($poolParentId, $companyName);
    }

    /**
     * Companies whose assigned account is named after a DIFFERENT company get
     * a corrected account, and their claim journal lines move with them.
     * Callers must recompute account balances afterwards.
     *
     * @return list<array{company: string, from: string, to: string, lines: int}>
     */
    public function repairMismatched(): array
    {
        $poolParentId = DB::table('accounts')->where('code', self::POOL_PARENT_CODE)->value('id');
        $guideAccounts = DB::table('accounts')->where('parent_id', $poolParentId)->get(['id', 'code', 'name'])->keyBy('id');

        $wrong = [];

        foreach (DB::table('insurance_companies')->whereNotNull('receivable_account_id')->get(['id', 'name', 'receivable_account_id']) as $company) {
            $account = $guideAccounts[$company->receivable_account_id] ?? null;

            if ($account && ! $this->namesMatch((string) $company->name, $this->accountCompanyName($account->name))) {
                $wrong[$company->id] = $company;
            }
        }

        foreach ($wrong as $company) {
            DB::table('insurance_companies')->where('id', $company->id)->update(['receivable_account_id' => null]);
        }

        $report = [];

        foreach ($wrong as $company) {
            $newId = $this->resolve($company->id);

            $report[] = [
                'company' => (string) $company->name,
                'from' => (string) $guideAccounts[$company->receivable_account_id]->code,
                'to' => (string) DB::table('accounts')->where('id', $newId)->value('code'),
                'lines' => $newId === $company->receivable_account_id
                    ? 0
                    : $this->moveClaimLines($company->id, $company->receivable_account_id, $newId),
            ];
        }

        return $report;
    }

    private function moveClaimLines(string $companyId, string $fromAccountId, string $toAccountId): int
    {
        $moved = 0;

        foreach (DB::table('insurance_claims')->where('insurance_company_id', $companyId)->pluck('id')->chunk(100) as $claimIds) {
            $entryIds = DB::table('journal_entries')
                ->where(function ($query) use ($claimIds) {
                    foreach ($claimIds as $claimId) {
                        $query->orWhere('idempotency_key', 'like', "insurance_claim_%:{$claimId}%");
                    }
                })
                ->pluck('id');

            $entryIds = $entryIds->merge(DB::table('journal_entries')->whereIn('reversal_of_id', $entryIds)->pluck('id'));

            $moved += DB::table('journal_entries')->whereIn('id', $entryIds)
                ->where('debit_account_id', $fromAccountId)->update(['debit_account_id' => $toAccountId]);
            $moved += DB::table('journal_entries')->whereIn('id', $entryIds)
                ->where('credit_account_id', $fromAccountId)->update(['credit_account_id' => $toAccountId]);
        }

        return $moved;
    }

    private function createDedicatedAccount(string $poolParentId, string $companyName): string
    {
        $used = DB::table('accounts')->whereBetween('code', [(string) self::DEDICATED_FIRST, (string) self::DEDICATED_LAST])->pluck('code')->all();

        for ($code = self::DEDICATED_FIRST; $code <= self::DEDICATED_LAST; $code++) {
            if (in_array((string) $code, $used, true)) {
                continue;
            }

            $id = (string) Str::ulid();

            DB::table('accounts')->insert([
                'id' => $id,
                'code' => (string) $code,
                'name' => mb_substr(self::NAME_PREFIX.trim($companyName), 0, 150),
                'group' => 'assets',
                'nature' => 'debit',
                'parent_id' => $poolParentId,
                'balance' => 0,
                'is_active' => true,
                'is_postable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        }

        throw new AccountingException('نطاق حسابات شركات التأمين الإضافية (1061–1079) ممتلئ — أضف نطاقًا جديدًا في الدليل.');
    }

    private function accountCompanyName(string $accountName): string
    {
        return str_replace(self::NAME_PREFIX, '', $accountName);
    }

    /** Same company regardless of diacritics, titles or word order. */
    private function namesMatch(string $a, string $b): bool
    {
        $words = function (string $name): array {
            $parts = array_values(array_filter(explode(' ', SubledgerAccountResolver::normalizeName($name))));
            sort($parts);

            return $parts;
        };

        $left = $words($a);

        return $left !== [] && $left === $words($b);
    }
}
