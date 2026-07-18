<?php

namespace Modules\Accounting\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Accounting\Repositories\Contracts\TreasuryRepositoryInterface;

class TreasuryService
{
    public function __construct(private readonly TreasuryRepositoryInterface $treasuryRepository) {}

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
}
