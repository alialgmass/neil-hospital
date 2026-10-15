<?php

namespace Modules\Accounting\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\TreasuryEntry;

interface TreasuryRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator;

    public function create(array $data): TreasuryEntry;

    public function balance(?string $upToDate = null): array;

    public function findOrFail(string $id): TreasuryEntry;
}
