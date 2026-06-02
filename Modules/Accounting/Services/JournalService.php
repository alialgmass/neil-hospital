<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Repositories\Contracts\JournalRepositoryInterface;

class JournalService
{
    public function __construct(private readonly JournalRepositoryInterface $journalRepository) {}

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

    public function record(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $entry = $this->journalRepository->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

            $this->adjustBalance($data['debit_account_id'], $data['amount'], AccountNature::Debit);
            $this->adjustBalance($data['credit_account_id'], $data['amount'], AccountNature::Credit);

            return $entry;
        });
    }

    /** Returns only leaf (postable) accounts — no parent/summary accounts. */
    public function accounts(): Collection
    {
        return Account::whereDoesntHave('children')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function adjustBalance(string $accountId, float $amount, AccountNature $side): void
    {
        $account = Account::select('id', 'nature')->findOrFail($accountId);
        $adjustment = ($account->nature === $side) ? $amount : -$amount;
        DB::table('accounts')->where('id', $accountId)->increment('balance', $adjustment);
    }
}
