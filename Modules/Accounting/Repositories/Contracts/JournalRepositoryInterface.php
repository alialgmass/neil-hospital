<?php

namespace Modules\Accounting\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\JournalEntry;

interface JournalRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator;

    public function create(array $data): JournalEntry;

    public function findOrFail(string $id): JournalEntry;
}
