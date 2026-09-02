<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Repositories\Contracts\JournalRepositoryInterface;

class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journalRepository,
        private readonly AccountResolver $accountResolver,
    ) {}

    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return $this->journalRepository->paginate($filters, $perPage);
    }

    public function totalAmount(array $filters = []): float
    {
        return (float) JournalEntry::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['cost_center'] ?? null, fn ($q, $v) => $q->where('cost_center', $v))
            ->sum('amount');
    }

    /**
     * Post a journal entry.
     *
     * Validates:
     * - amount > 0
     * - debit_account_id !== credit_account_id
     * - both accounts exist, are active, and are postable (not parent/summary accounts)
     *
     * If `idempotency_key` is present and an entry with that key already
     * exists, the existing entry is returned unchanged (no-op) instead of
     * creating a duplicate.
     *
     * @throws AccountingException
     */
    public function record(array $data): JournalEntry
    {
        if (! empty($data['idempotency_key'])) {
            $existing = JournalEntry::where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                return $existing;
            }
        }

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new AccountingException('Journal entry amount must be greater than zero.');
        }

        if (($data['debit_account_id'] ?? null) === ($data['credit_account_id'] ?? null)) {
            throw new AccountingException('Debit and credit accounts cannot be the same account.');
        }

        $this->accountResolver->mustBePostableAndActive($data['debit_account_id']);
        $this->accountResolver->mustBePostableAndActive($data['credit_account_id']);

        try {
            return DB::transaction(function () use ($data) {
                $entry = $this->journalRepository->create([
                    ...$data,
                    'created_by' => auth()->id(),
                ]);

                $this->adjustBalance($data['debit_account_id'], (float) $data['amount'], AccountNature::Debit);
                $this->adjustBalance($data['credit_account_id'], (float) $data['amount'], AccountNature::Credit);

                return $entry;
            });
        } catch (QueryException $e) {
            // Unique idempotency_key race: another concurrent request won — return that entry.
            if (! empty($data['idempotency_key']) && str_contains($e->getMessage(), 'idempotency_key')) {
                return JournalEntry::where('idempotency_key', $data['idempotency_key'])->firstOrFail();
            }

            throw $e;
        }
    }

    /**
     * Reverse a previously-posted journal entry using its ORIGINAL amount
     * and accounts (never recalculated). No-ops if the entry has already
     * been reversed (double-reversal guard).
     */
    public function reverse(
        JournalEntry $entry,
        JournalSource $reversalSource,
        string $reference,
        ?string $description = null,
        ?string $date = null,
    ): ?JournalEntry {
        if ($entry->reversed_at !== null) {
            return null;
        }

        return DB::transaction(function () use ($entry, $reversalSource, $reference, $description, $date) {
            // Re-lock/check inside the transaction to close the race window.
            $fresh = JournalEntry::whereKey($entry->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->reversed_at !== null) {
                return null;
            }

            $reversal = $this->record([
                'date' => $date ?? today()->toDateString(),
                'description' => $description ?? "عكس قيد: {$fresh->description}",
                'debit_account_id' => $fresh->credit_account_id,
                'credit_account_id' => $fresh->debit_account_id,
                'amount' => (float) $fresh->amount,
                'source' => $reversalSource,
                'reference' => $reference,
                'reversal_of_id' => $fresh->id,
                'cost_center' => $fresh->cost_center,
            ]);

            $fresh->update(['reversed_at' => now()]);

            return $reversal;
        });
    }

    /** Returns only leaf (postable) accounts — no parent/summary accounts. */
    public function accounts(): Collection
    {
        return Account::where('is_postable', true)
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();
    }

    /**
     * Delete a manual journal entry — via a reversing entry (offsetting
     * debit/credit, archive preserved), not a hard delete. Entries posted by
     * the system (booking/purchase/etc.) can't be deleted from here — they
     * must be reversed from their originating screen so upstream records
     * (bookings, invoices, ...) stay consistent.
     *
     * @throws ValidationException if the entry isn't an editable manual entry.
     */
    public function delete(string $id): void
    {
        $entry = $this->journalRepository->findOrFail($id);

        if ($entry->source !== JournalSource::MANUAL || $entry->reversal_of_id !== null) {
            throw ValidationException::withMessages([
                'source' => 'هذا القيد مرتبط بمعاملة أخرى — يجب حذفه/عكسه من شاشتها الأصلية.',
            ]);
        }

        if ($entry->reversed_at !== null) {
            throw ValidationException::withMessages([
                'source' => 'هذا القيد معكوس بالفعل.',
            ]);
        }

        $this->reverse(
            entry: $entry,
            reversalSource: JournalSource::REVERSAL,
            reference: $entry->reference ?? "REV-{$entry->id}",
        );
    }

    private function adjustBalance(string $accountId, float $amount, AccountNature $side): void
    {
        $account = Account::select('id', 'nature')->findOrFail($accountId);
        $adjustment = ($account->nature === $side) ? $amount : -$amount;
        DB::table('accounts')->where('id', $accountId)->increment('balance', $adjustment);
    }
}
