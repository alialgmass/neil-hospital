<?php

namespace Modules\Inventory\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Inventory\Enums\CenterShareType;
use Modules\Inventory\Models\Service;

class MedicalServiceService
{
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Service::query()
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($filters['dept'] ?? null, fn ($q, $v) => $q->where('dept', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('dept')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Service
    {
        return Service::create($this->calculateShares($data));
    }

    public function update(string $id, array $data): Service
    {
        $service = Service::findOrFail($id);
        $service->update($this->calculateShares($data));

        return $service;
    }

    public function delete(string $id): void
    {
        Service::findOrFail($id)->delete();
    }

    public function toggleStatus(string $id, string $status): void
    {
        Service::where('id', $id)->update(['status' => $status]);
    }

    private function calculateShares(array $data): array
    {
        $price = (float) ($data['price'] ?? 0);
        $val = (float) ($data['center_val'] ?? 0);
        $type = $data['center_type'] instanceof CenterShareType ? $data['center_type'] : CenterShareType::from($data['center_type'] ?? 'pct');

        $centerShare = ($type === CenterShareType::Percentage)
            ? round($price * $val / 100, 2)
            : $val;

        $data['center_share'] = $centerShare;
        $data['center_type'] = $type;
        $data['dr_share'] = round($price - $centerShare, 2);

        return $data;
    }
}
