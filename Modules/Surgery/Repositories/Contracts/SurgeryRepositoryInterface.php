<?php

namespace Modules\Surgery\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Surgery\Models\Surgery;

interface SurgeryRepositoryInterface
{
    public function paginateByDept(string $dept, ?string $status = null, int $perPage = 20): LengthAwarePaginator;

    public function findOrFail(string $id): Surgery;

    public function create(array $data): Surgery;

    public function update(string $id, array $data): Surgery;

    public function findByBooking(string $bookingId): ?Surgery;
}
