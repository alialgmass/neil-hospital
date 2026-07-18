<?php

namespace Modules\Admin\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\Models\Service;

class ServiceManagementService
{
    public function list(?string $dept = null, ?string $search = null, int $perPage = 40): LengthAwarePaginator
    {
        return Service::query()
            ->when($dept, fn ($q, $v) => $q->where('dept', $v))
            ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('dept')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
