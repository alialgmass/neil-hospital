<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function record(array $data): JournalEntry
    {
        $entry = $this->journalRepository->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);

        // Update account balances
        $this->adjustBalance($data['debit_account_id'], $data['amount'], AccountNature::Debit);
        $this->adjustBalance($data['credit_account_id'], $data['amount'], AccountNature::Credit);

        return $entry;
    }

    public function accounts(): Collection
    {
        return Account::where('is_active', true)->orderBy('code')->get();
    }

    private function adjustBalance(string $accountId, float $amount, AccountNature $side): void
    {
        $account = Account::findOrFail($accountId);

        // Debit increases debit-nature accounts; credit increases credit-nature
        $adjustment = ($account->nature === $side) ? $amount : -$amount;

        $account->increment('balance', $adjustment);
    }
}
