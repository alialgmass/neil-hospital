<?php

namespace Modules\Surgery\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Repositories\Contracts\SurgeryRepositoryInterface;

class SurgeryService
{
    public function __construct(private readonly SurgeryRepositoryInterface $surgeryRepository) {}

    public function list(string $dept, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->surgeryRepository->paginateByDept($dept, $status, $perPage);
    }

    public function findOrFail(string $id): Surgery
    {
        return $this->surgeryRepository->findOrFail($id);
    }

    public function schedule(SurgeryData $data): Surgery
    {
        return $this->surgeryRepository->create([
            'booking_id' => $data->bookingId,
            'dept' => $data->dept,
            'or_bed_id' => $data->orBedId,
            'surgeon_id' => $data->surgeonId,
            'eye' => $data->eye,
            'procedure' => $data->procedure,
            'anaesthesia' => $data->anaesthesia,
            'status' => $data->status,
            'pre_op_notes' => $data->preOpNotes,
            'scheduled_at' => $data->scheduledAt,
        ]);
    }

    public function isBedAvailable(int $bedId, string $scheduledAt): bool
    {
        // A bed is "available" if no in-progress or prep surgery is scheduled at the same time
        return ! Surgery::where('or_bed_id', $bedId)
            ->whereIn('status', ['scheduled', 'prep', 'in_progress'])
            ->whereDate('scheduled_at', date('Y-m-d', strtotime($scheduledAt)))
            ->exists();
    }

    public function recordSupplies(SuppliesUsedData $data): Surgery
    {
        $total = $data->total();

        return $this->surgeryRepository->update($data->surgeryId, [
            'supplies_used' => $data->items,
            'supply_total' => $total,
        ]);
    }

    public function getAvailableBeds(): Collection
    {
        return OrBed::with('room')
            ->where('status', 'available')
            ->get();
    }
}
