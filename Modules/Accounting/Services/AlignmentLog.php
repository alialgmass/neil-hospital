<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Exceptions\AccountingException;

/**
 * Write-ahead log for the الدليل المحاسبي v2.0 data migrations. Every row a
 * migration changes or creates goes through here, so `revert($batch)` can
 * undo that migration exactly — the migrations stay reversible even though
 * they move live balances between accounts.
 *
 * Also owns the two ledger-wide invariants checked after every phase:
 * stored `accounts.balance` is recomputed from `journal_entries`, and the
 * trial balance must foot (total debits = total credits).
 */
class AlignmentLog
{
    private const TABLE = 'accounting_alignment_log';

    public function update(string $batch, string $table, string $id, string $column, mixed $newValue): void
    {
        $oldValue = DB::table($table)->where('id', $id)->value($column);

        if ((string) $oldValue === (string) $newValue && ($oldValue === null) === ($newValue === null)) {
            return;
        }

        DB::table($table)->where('id', $id)->update([$column => $newValue]);

        DB::table(self::TABLE)->insert([
            'batch' => $batch,
            'table_name' => $table,
            'record_id' => $id,
            'column_name' => $column,
            'old_value' => $oldValue === null ? null : (string) $oldValue,
            'new_value' => $newValue === null ? null : (string) $newValue,
            'operation' => 'update',
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $row  must include `id` */
    public function insert(string $batch, string $table, array $row): void
    {
        DB::table($table)->insert($row);

        DB::table(self::TABLE)->insert([
            'batch' => $batch,
            'table_name' => $table,
            'record_id' => (string) $row['id'],
            'operation' => 'insert',
            'created_at' => now(),
        ]);
    }

    /** Record a row some other component already inserted, so revert() deletes it. */
    public function recordInsert(string $batch, string $table, string $id): void
    {
        DB::table(self::TABLE)->insert([
            'batch' => $batch,
            'table_name' => $table,
            'record_id' => $id,
            'operation' => 'insert',
            'created_at' => now(),
        ]);
    }

    /** Record a column some other component already changed, so revert() restores it. */
    public function recordUpdate(string $batch, string $table, string $id, string $column, mixed $oldValue, mixed $newValue): void
    {
        DB::table(self::TABLE)->insert([
            'batch' => $batch,
            'table_name' => $table,
            'record_id' => $id,
            'column_name' => $column,
            'old_value' => $oldValue === null ? null : (string) $oldValue,
            'new_value' => $newValue === null ? null : (string) $newValue,
            'operation' => 'update',
            'created_at' => now(),
        ]);
    }

    /** Undo every change recorded under $batch, newest first. */
    public function revert(string $batch): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $entries = DB::table(self::TABLE)->where('batch', $batch)->orderByDesc('id')->get();

        foreach ($entries as $entry) {
            if ($entry->operation === 'insert') {
                DB::table($entry->table_name)->where('id', $entry->record_id)->delete();

                continue;
            }

            DB::table($entry->table_name)->where('id', $entry->record_id)->update([$entry->column_name => $entry->old_value]);
        }

        DB::table(self::TABLE)->where('batch', $batch)->delete();
    }

    /**
     * Rebuild every `accounts.balance` from the journal (natural-side
     * balance: debits − credits for debit-nature accounts, the reverse for
     * credit-nature ones). Used after lines are re-pointed between accounts.
     */
    public function recomputeBalances(): void
    {
        $debits = DB::table('journal_entries')->selectRaw('debit_account_id as account_id, SUM(amount) as total')
            ->groupBy('debit_account_id')->pluck('total', 'account_id');
        $credits = DB::table('journal_entries')->selectRaw('credit_account_id as account_id, SUM(amount) as total')
            ->groupBy('credit_account_id')->pluck('total', 'account_id');

        foreach (DB::table('accounts')->get(['id', 'nature', 'balance']) as $account) {
            $net = (float) ($debits[$account->id] ?? 0) - (float) ($credits[$account->id] ?? 0);
            $balance = round($account->nature === 'debit' ? $net : -$net, 2);

            if (round((float) $account->balance, 2) !== $balance) {
                DB::table('accounts')->where('id', $account->id)->update(['balance' => $balance]);
            }
        }
    }

    /**
     * The trial balance must foot: Σ debit-side balances = Σ credit-side
     * balances across every account, using the stored balances.
     *
     * @throws AccountingException
     */
    public function assertTrialBalanceBalanced(string $phase): void
    {
        $debitSide = 0.0;
        $creditSide = 0.0;

        foreach (DB::table('accounts')->get(['nature', 'balance']) as $account) {
            $balance = (float) $account->balance;
            $isDebitSide = ($account->nature === 'debit') === ($balance >= 0);

            if ($isDebitSide) {
                $debitSide += abs($balance);
            } else {
                $creditSide += abs($balance);
            }
        }

        if (abs(round($debitSide - $creditSide, 2)) >= 0.01) {
            throw new AccountingException(sprintf(
                'ميزان المراجعة غير متوازن بعد مرحلة [%s]: مدين %.2f ≠ دائن %.2f',
                $phase,
                $debitSide,
                $creditSide,
            ));
        }
    }
}
