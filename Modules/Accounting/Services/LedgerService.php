<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;

class LedgerService
{
    /**
     * Trial balance: all accounts with their debit/credit totals and running balance.
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $accounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get();

        return $accounts->map(function (Account $account) use ($from, $to) {
            $debits = DB::table('journal_entries')
                ->where('debit_account_id', $account->id)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->sum('amount');

            $credits = DB::table('journal_entries')
                ->where('credit_account_id', $account->id)
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->sum('amount');

            return [
                'code' => $account->code,
                'name' => $account->name,
                'group' => $account->group,
                'nature' => $account->nature,
                'debits' => (float) $debits,
                'credits' => (float) $credits,
                'balance' => $account->nature === AccountNature::Debit
                    ? (float) ($debits - $credits)
                    : (float) ($credits - $debits),
            ];
        })->toArray();
    }

    /**
     * Account statement: all journal movements for a single account.
     */
    public function accountStatement(string $accountId, ?string $from = null, ?string $to = null): array
    {
        $account = Account::findOrFail($accountId);

        $debitRows = DB::table('journal_entries')
            ->where('debit_account_id', $accountId)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->select('date', 'description', 'amount as debit', DB::raw('0 as credit'), 'reference')
            ->get();

        $creditRows = DB::table('journal_entries')
            ->where('credit_account_id', $accountId)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->select('date', 'description', DB::raw('0 as debit'), 'amount as credit', 'reference')
            ->get();

        $rows = $debitRows->concat($creditRows)
            ->sortBy('date')
            ->values();

        $runningBalance = 0.0;
        $statement = $rows->map(function ($row) use (&$runningBalance, $account) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            if ($account->nature === AccountNature::Debit) {
                $runningBalance += $debit - $credit;
            } else {
                $runningBalance += $credit - $debit;
            }

            return [
                'date' => $row->date,
                'description' => $row->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
                'reference' => $row->reference,
            ];
        });

        return [
            'account' => ['code' => $account->code, 'name' => $account->name],
            'statement' => $statement->toArray(),
        ];
    }
}
