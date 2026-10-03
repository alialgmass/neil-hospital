<?php

namespace Modules\Accounting\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\ReverseTreasuryEntryAction;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Accounting\Repositories\Contracts\TreasuryRepositoryInterface;

class TreasuryService
{
    public function __construct(
        private readonly TreasuryRepositoryInterface $treasuryRepository,
        private readonly ReverseTreasuryEntryAction $reverseAction,
    ) {}

    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return $this->treasuryRepository->paginate($filters, $perPage);
    }

    public function record(array $data): TreasuryEntry
    {
        return $this->treasuryRepository->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }

    public function findOrFail(string $id): TreasuryEntry
    {
        return $this->treasuryRepository->findOrFail($id);
    }

    /**
     * Edit a manual treasury entry: reverses the original (offsetting entry,
     * archive preserved) and records a fresh entry with the updated data.
     *
     * @throws ValidationException if the entry isn't an editable manual entry.
     */
    public function update(string $id, array $data): TreasuryEntry
    {
        $entry = $this->assertEditable($id);

        $this->reverseAction->execute($entry);

        return $this->record($data);
    }

    /**
     * Delete a manual treasury entry: reverses it via an offsetting entry
     * instead of removing the row, preserving the movement history.
     *
     * @throws ValidationException if the entry isn't an editable manual entry.
     */
    public function delete(string $id): void
    {
        $entry = $this->assertEditable($id);

        $this->reverseAction->execute($entry);
    }

    /**
     * Treasury movement statement with a running balance, sourced directly
     * from treasury_entries (manual entries are never posted to the journal).
     */
    public function statement(?string $from = null, ?string $to = null): array
    {
        $openingBalance = $from
            ? $this->treasuryRepository->balance(Carbon::parse($from)->subDay()->toDateString())['balance']
            : 0.0;

        $rows = TreasuryEntry::query()
            ->when($from, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $runningBalance = (float) $openingBalance;

        $statement = $rows->map(function (TreasuryEntry $entry) use (&$runningBalance) {
            $in = $entry->type === TreasuryType::In ? (float) $entry->amount : 0.0;
            $out = $entry->type === TreasuryType::Out ? (float) $entry->amount : 0.0;
            $runningBalance += $in - $out;

            return [
                'date' => $entry->date->toDateString(),
                'description' => $entry->description,
                'in' => $in,
                'out' => $out,
                'balance' => $runningBalance,
                'reference' => $entry->reference_no,
            ];
        });

        return [
            'opening_balance' => (float) $openingBalance,
            'statement' => $statement->toArray(),
        ];
    }

    public function balance(?string $upToDate = null): array
    {
        return $this->treasuryRepository->balance($upToDate);
    }

    public function todayNet(): float
    {
        $in = TreasuryEntry::where('type', TreasuryType::In)->whereDate('date', today())->sum('amount');
        $out = TreasuryEntry::where('type', TreasuryType::Out)->whereDate('date', today())->sum('amount');

        return (float) ($in - $out);
    }

    /**
     * @throws ValidationException
     */
    private function assertEditable(string $id): TreasuryEntry
    {
        $entry = $this->treasuryRepository->findOrFail($id);

        if ($entry->source !== 'manual' || $entry->reversal_of_id !== null) {
            throw ValidationException::withMessages([
                'source' => 'هذه الحركة مرتبطة بمعاملة أخرى (حجز/فاتورة/عكس قيد) — يجب تعديلها أو حذفها من شاشتها الأصلية.',
            ]);
        }

        if ($entry->reversed_at !== null) {
            throw ValidationException::withMessages([
                'source' => 'هذه الحركة معكوسة بالفعل.',
            ]);
        }

        return $entry;
    }
}
