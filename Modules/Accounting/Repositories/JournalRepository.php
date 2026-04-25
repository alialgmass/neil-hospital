<?php

namespace Modules\Accounting\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Repositories\Contracts\JournalRepositoryInterface;

class JournalRepository implements JournalRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with(['debitAccount', 'creditAccount', 'creator'])
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): JournalEntry
    {
        return JournalEntry::create($data);
    }
}
