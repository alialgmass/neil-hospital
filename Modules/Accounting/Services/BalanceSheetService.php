<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Enums\Subledger;
use Modules\Accounting\Models\Account;

/**
 * Minimal Balance Sheet: Assets vs Liabilities + Equity, as of a date.
 * Classifies purely by Account::group/nature (never by code-prefix ranges),
 * consistent with LedgerService/IncomeStatementService.
 *
 * Per guide §3.6: the per-party sub-ledgers (2201–… / 2301–… / 2401–…) are
 * shown as their control account's total (2010 / 2020 / 2030), and the
 * not-yet-closed result of the period (revenues − expenses) is shown inside
 * equity, so total assets = total liabilities + equity before year-end closing.
 */
class BalanceSheetService
{
    public function get(?string $asOf = null): array
    {
        $accounts = Account::where('is_active', true)
            ->moduleEnabled()
            ->orderBy('code')
            ->get();

        $debitTotals = $this->sumsBySide('debit_account_id', $asOf);
        $creditTotals = $this->sumsBySide('credit_account_id', $asOf);

        $controlByChildParent = $accounts
            ->filter(fn (Account $account) => Subledger::forMasterCode($account->code) !== null)
            ->keyBy('id');

        $sections = [
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
        ];

        $totals = ['assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0];
        $controlRows = [];
        $netResult = 0.0;

        foreach ($accounts as $account) {
            $debits = (float) ($debitTotals[$account->id] ?? 0);
            $credits = (float) ($creditTotals[$account->id] ?? 0);

            $balance = $account->nature === AccountNature::Debit
                ? $debits - $credits
                : $credits - $debits;

            if (in_array($account->group, [AccountGroup::Revenues, AccountGroup::Expenses], true)) {
                // Contra-revenue accounts are debit-nature; credits − debits is the profit effect for all P&L lines.
                $netResult += $credits - $debits;

                continue;
            }

            if ($balance == 0.0 && $debits == 0.0 && $credits == 0.0) {
                continue;
            }

            $key = match ($account->group) {
                AccountGroup::Assets => 'assets',
                AccountGroup::Liabilities => 'liabilities',
                AccountGroup::Equity => 'equity',
                default => null,
            };

            $control = $controlByChildParent->get($account->parent_id);

            if ($control !== null) {
                $controlRows[$control->code] ??= ['code' => $control->code, 'name' => $control->name, 'balance' => 0.0, 'section' => $key];
                $controlRows[$control->code]['balance'] += $balance;
            } else {
                $sections[$key][] = ['code' => $account->code, 'name' => $account->name, 'balance' => $balance];
            }

            $totals[$key] += $balance;
        }

        foreach ($controlRows as $row) {
            $sections[$row['section']][] = ['code' => $row['code'], 'name' => $row['name'], 'balance' => round($row['balance'], 2)];
        }

        $sections['liabilities'] = collect($sections['liabilities'])->sortBy('code')->values()->all();

        if (round($netResult, 2) != 0.0) {
            $sections['equity'][] = [
                'code' => '—',
                'name' => $netResult >= 0 ? 'صافي ربح الفترة (لم يُقفل بعد)' : 'صافي (خسارة) الفترة (لم يُقفل بعد)',
                'balance' => round($netResult, 2),
            ];
            $totals['equity'] += $netResult;
        }

        return [
            'asOf' => $asOf ?? now()->toDateString(),
            'assets' => $sections['assets'],
            'liabilities' => $sections['liabilities'],
            'equity' => $sections['equity'],
            'totalAssets' => round($totals['assets'], 2),
            'totalLiabilities' => round($totals['liabilities'], 2),
            'totalEquity' => round($totals['equity'], 2),
            'totalLiabilitiesAndEquity' => round($totals['liabilities'] + $totals['equity'], 2),
            'isBalanced' => abs($totals['assets'] - ($totals['liabilities'] + $totals['equity'])) < 0.01,
        ];
    }

    /** @return Collection<string, float> */
    private function sumsBySide(string $column, ?string $asOf): Collection
    {
        return DB::table('journal_entries')
            ->when($asOf, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->groupBy($column)
            ->selectRaw("{$column} as account_id, SUM(amount) as total")
            ->pluck('total', 'account_id');
    }
}
