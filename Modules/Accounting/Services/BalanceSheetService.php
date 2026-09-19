<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;

/**
 * Minimal Balance Sheet: Assets vs Liabilities + Equity, as of a date.
 * Classifies purely by Account::group/nature (never by code-prefix ranges),
 * consistent with LedgerService/IncomeStatementService.
 */
class BalanceSheetService
{
    public function get(?string $asOf = null): array
    {
        $accounts = Account::where('is_active', true)
            ->moduleEnabled()
            ->orderBy('code')
            ->get();

        $sections = [
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
        ];

        $totals = ['assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0];

        foreach ($accounts as $account) {
            if (! in_array($account->group, [AccountGroup::Assets, AccountGroup::Liabilities, AccountGroup::Equity], true)) {
                continue;
            }

            $debits = (float) DB::table('journal_entries')
                ->where('debit_account_id', $account->id)
                ->when($asOf, fn ($q, $v) => $q->whereDate('date', '<=', $v))
                ->sum('amount');

            $credits = (float) DB::table('journal_entries')
                ->where('credit_account_id', $account->id)
                ->when($asOf, fn ($q, $v) => $q->whereDate('date', '<=', $v))
                ->sum('amount');

            $balance = $account->nature === AccountNature::Debit
                ? $debits - $credits
                : $credits - $debits;

            if ($balance == 0.0 && $debits == 0.0 && $credits == 0.0) {
                continue;
            }

            $row = ['code' => $account->code, 'name' => $account->name, 'balance' => $balance];

            $key = match ($account->group) {
                AccountGroup::Assets => 'assets',
                AccountGroup::Liabilities => 'liabilities',
                AccountGroup::Equity => 'equity',
                default => null,
            };

            $sections[$key][] = $row;
            $totals[$key] += $balance;
        }

        return [
            'asOf' => $asOf ?? now()->toDateString(),
            'assets' => $sections['assets'],
            'liabilities' => $sections['liabilities'],
            'equity' => $sections['equity'],
            'totalAssets' => $totals['assets'],
            'totalLiabilities' => $totals['liabilities'],
            'totalEquity' => $totals['equity'],
            'totalLiabilitiesAndEquity' => $totals['liabilities'] + $totals['equity'],
            'isBalanced' => abs($totals['assets'] - ($totals['liabilities'] + $totals['equity'])) < 0.01,
        ];
    }
}
