<?php

namespace Modules\Accounting\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Accounting\Repositories\Contracts\TreasuryRepositoryInterface;

class TreasuryRepository implements TreasuryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return TreasuryEntry::query()
            ->with(['account', 'creator'])
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): TreasuryEntry
    {
        return TreasuryEntry::create($data);
    }

    public function balance(?string $upToDate = null): array
    {
        $query = TreasuryEntry::query()
            ->when($upToDate, fn ($q) => $q->whereDate('date', '<=', $upToDate));

        $totalIn = (clone $query)->where('type', 'in')->sum('amount');
        $totalOut = (clone $query)->where('type', 'out')->sum('amount');

        return [
            'total_in' => (float) $totalIn,
            'total_out' => (float) $totalOut,
            'balance' => (float) ($totalIn - $totalOut),
        ];
    }
}
