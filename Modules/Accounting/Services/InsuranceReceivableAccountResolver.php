<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves each insurance company's own receivable sub-account (1031–1047)
 * instead of the shared aggregate 1030 (non-postable, reporting roll-up
 * only). A company is lazily assigned the next free sub-account the first
 * time a claim is posted for it, and keeps that account from then on.
 */
class InsuranceReceivableAccountResolver
{
    private const POOL_PARENT_CODE = '1030';

    private const NAME_PREFIX = 'ذمم تأمين — ';

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
     * called "نفقة الدولة" gets 1031 "ذمم تأمين — نفقة الدولة" — or else the
     * next 1031–1047 sub-account not already assigned to another company.
     * Falls back to the aggregate 1030 roll-up only if the whole pool is
     * exhausted (17 companies already assigned) — better than failing the
     * claim outright, but should never happen in practice.
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

        $companyName = SubledgerAccountResolver::normalizeName((string) DB::table('insurance_companies')->where('id', $insuranceCompanyId)->value('name'));
        $byName = $free->first(fn ($account) => $companyName !== ''
            && SubledgerAccountResolver::normalizeName(str_replace(self::NAME_PREFIX, '', (string) $account->name)) === $companyName);

        return $byName->id
            ?? $free->first()->id
            ?? DB::table('accounts')->where('code', self::POOL_PARENT_CODE)->value('id');
    }
}
