<?php

namespace Modules\Surgery\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\Models\Booking;
use Modules\Booking\States\ConfirmedState as BookingConfirmedState;
use Modules\Booking\States\WaitingState as BookingWaitingState;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Actions\UpdateSurgeryStatusAction;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Models\OrBed;
use Modules\Surgery\Models\OrRoom;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Repositories\Contracts\SurgeryRepositoryInterface;
use Modules\Surgery\States\InProgressState;
use Modules\Surgery\States\PrepState;
use Modules\Surgery\States\ScheduledState;

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

    public function findByBooking(string $bookingId): ?Surgery
    {
        return $this->surgeryRepository->findByBooking($bookingId);
    }

    public function schedule(SurgeryData $data): Surgery
    {
        return $this->surgeryRepository->create([
            'booking_id' => $data->bookingId,
            'dept' => $data->dept,
            'or_bed_id' => $data->orBedId,
            'bed_no' => $data->bedNo,
            'surgeon_id' => $data->surgeonId,
            'eye' => $data->eye,
            'procedure' => $data->procedure,
            'anaesthesia' => $data->anaesthesia,
            'status' => $data->status,
            'pre_op_notes' => $data->preOpNotes,
            'scheduled_at' => $data->scheduledAt,
        ]);
    }

    public function update(string $id, SurgeryData $data): Surgery
    {
        return $this->surgeryRepository->update($id, [
            'or_bed_id' => $data->orBedId,
            'bed_no' => $data->bedNo,
            'surgeon_id' => $data->surgeonId,
            'eye' => $data->eye,
            'procedure' => $data->procedure,
            'anaesthesia' => $data->anaesthesia,
            'status' => $data->status,
            'pre_op_notes' => $data->preOpNotes,
            'scheduled_at' => $data->scheduledAt,
        ]);
    }

    public function updateStatusByBooking(string $bookingId, string $status): void
    {
        $surgery = $this->findByBooking($bookingId);
        if ($surgery) {
            app(UpdateSurgeryStatusAction::class)->execute($surgery->id, $status);
        }
    }

    public function isBedAvailable(int $bedId, string $scheduledAt, ?string $excludeSurgeryId = null): bool
    {
        return ! Surgery::where('or_bed_id', $bedId)
            ->when($excludeSurgeryId, fn ($q) => $q->where('id', '!=', $excludeSurgeryId))
            ->where(function ($query) use ($scheduledAt) {
                // 1. Physically occupied by an active surgery right now
                $query->whereIn('status', [PrepState::$name, InProgressState::$name])
                    // 2. OR reserved for someone else on the exact same date
                    ->orWhere(function ($q) use ($scheduledAt) {
                        $q->where('status', ScheduledState::$name)
                            ->whereDate('scheduled_at', date('Y-m-d', strtotime($scheduledAt)));
                    });
            })
            ->exists();
    }

    public function recordSupplies(SuppliesUsedData $data): Surgery
    {
        $surgery = Surgery::findOrFail($data->surgeryId);
        $existing = $surgery->supplies_used ?? [];
        $newItems = $data->items;

        $merged = array_merge($existing, $newItems);
        $total = array_sum(array_map(fn ($item) => (float) ($item['total'] ?? 0), $merged));

        return $this->surgeryRepository->update($data->surgeryId, [
            'supplies_used' => $merged,
            'supply_total' => $total,
        ]);
    }

    /** Bookings that have no active surgery row yet (for the scheduling dropdown). */
    public function getUnscheduledBookings(string $dept): Collection
    {
        $scheduledIds = Surgery::where('dept', $dept)
            ->whereIn('status', [ScheduledState::$name, PrepState::$name, InProgressState::$name])
            ->pluck('booking_id');

        return Booking::where('dept', $dept)
            ->whereIn('status', [BookingWaitingState::$name, BookingConfirmedState::$name])
            ->whereNotIn('id', $scheduledIds)
            ->select('id', 'file_no', 'patient_name')
            ->orderByDesc('visit_date')
            ->get();
    }

    /** OR rooms with each bed's active surgery (any dept) so cross-dept occupancy is visible. */
    public function getOrRoomsWithBedStatus(string $dept, string $date): Collection
    {
        return OrRoom::with(['beds' => function ($q) {
            $q->orderBy('bed_number')
                ->with(['surgery' => function ($sq) {
                    $sq->whereIn('status', [ScheduledState::$name, PrepState::$name, InProgressState::$name])
                        ->with(['booking', 'surgeon']);
                }]);
        }])->orderBy('name')->get();
    }

    /** OR rooms for a given date — shows any active surgery regardless of dept (for booking bed picker). */
    public function getOrRoomsForDate(string $date): Collection
    {
        return OrRoom::with(['beds' => function ($q) {
            $q->orderBy('bed_number')
                ->with(['surgery' => function ($sq) {
                    $sq->whereIn('status', [ScheduledState::$name, PrepState::$name, InProgressState::$name]);
                }]);
        }])->orderBy('name')->get();
    }

    /** Total paid revenue for a given dept on today. */
    public function getTodayRevenue(string $dept): float
    {
        return (float) Booking::where('dept', $dept)
            ->whereDate('visit_date', today())
            ->sum('paid_amount');
    }

    public function getAvailableBeds(string $dept, string $date): Collection
    {
        return OrBed::with('room')
            ->whereDoesntHave('surgery', function ($q) use ($dept, $date) {
                $q->where('dept', $dept)
                    ->where(function ($iq) use ($date) {
                        $iq->whereIn('status', [PrepState::$name, InProgressState::$name])
                            ->orWhere(function ($iiq) use ($date) {
                                $iiq->where('status', ScheduledState::$name)
                                    ->whereDate('scheduled_at', $date);
                            });
                    });
            })
            ->get()
            ->map(fn (OrBed $bed) => [
                'id' => $bed->id,
                'label' => "{$bed->room->name} — سرير {$bed->bed_number}",
                'room' => $bed->room->name,
                'number' => $bed->bed_number,
            ]);
    }

    public function getActiveDoctors(): Collection
    {
        return Doctor::select('id', 'name')->orderBy('name')->get();
    }

    public function getActiveInventoryItems(): Collection
    {
        return InventoryItem::select('id', 'name', 'code', 'sell_price', 'quantity')
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get();
    }

    public function getActiveBundles(string $dept): Collection
    {
        return SupplyBundle::with('items')
            ->where('is_active', true)
            ->where(function ($q) use ($dept) {
                $q->where('dept', $dept)->orWhereNull('dept');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'dept', 'price', 'notes']);
    }
}
