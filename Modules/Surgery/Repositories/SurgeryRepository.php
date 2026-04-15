<?php

namespace Modules\Surgery\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Repositories\Contracts\SurgeryRepositoryInterface;

class SurgeryRepository extends BaseRepository implements SurgeryRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Surgery);
    }

    public function paginateByDept(string $dept, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return Surgery::with(['booking:id,patient_name,file_no', 'surgeon:id,name', 'orBed:id,bed_number'])
            ->where('dept', $dept)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('scheduled_at')
            ->paginate($perPage);
    }

    public function findOrFail(string $id): Surgery
    {
        /** @var Surgery */
        return Surgery::with(['booking', 'surgeon', 'orBed.room'])->findOrFail($id);
    }

    public function create(array $data): Surgery
    {
        return Surgery::create($data);
    }

    public function update(string $id, array $data): Surgery
    {
        $surgery = $this->findOrFail($id);
        $surgery->update($data);

        return $surgery->fresh();
    }

    public function findByBooking(string $bookingId): ?Surgery
    {
        return Surgery::where('booking_id', $bookingId)->first();
    }
}
