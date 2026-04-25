<?php

namespace Modules\Surgery\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\Models\Booking;
use Modules\Booking\States\ConfirmedState as BookingConfirmedState;
use Modules\Booking\States\WaitingState as BookingWaitingState;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Models\InventoryItem;
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
    public function __construct(private readonly SurgeryRepositoryInterface $surgeryRepository)
    {
    }

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
        if ($surgery)
            app(UpdateSurgeryStatusAction::class)->execute($surgery->id, $status);
    }

    public function isBedAvailable(int $bedId, string $scheduledAt, ?string $excludeSurgeryId = null): bool
    {
        return !Surgery::where('or_bed_id', $bedId)
            ->when($excludeSurgeryId, fn($q) => $q->where('id', '!=', $excludeSurgeryId))
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
        $total = $data->total();

        return $this->surgeryRepository->update($data->surgeryId, [
            'supplies_used' => $data->items,
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

    /** OR rooms with each bed's current active/scheduled surgery for a given date and dept. */
    public function getOrRoomsWithBedStatus(string $dept, string $date): Collection
    {
        return OrRoom::with(['beds' => function ($q) use ($dept, $date) {
            $q->orderBy('bed_number')
                ->with(['surgery' => function ($sq) use ($dept, $date) {
                    $sq->where('dept', $dept)
//                        ->where(function ($iq) use ($date) {
//                            $iq->whereIn('status', [PrepState::$name, InProgressState::$name])
//                                ->orWhere(function ($iiq) use ($date) {
//                                    $iiq->where('status', ScheduledState::$name)
//                                        ->whereDate('scheduled_at', $date);
//                                });
//                        })
                        ->with(['booking', 'surgeon']);
                }]);
        }])->orderBy('name')->get();
    }

    /** OR rooms for a given date, without dept filter (used for booking form bed picker). */
    public function getOrRoomsForDate(string $date): Collection
    {
        return OrRoom::with(['beds' => function ($q) use ($date) {
            $q->orderBy('bed_number')
                ->with(['surgery' => function ($sq) use ($date) {
//                    $sq->whereIn('status', [PrepState::$name, InProgressState::$name])
//                        ->orWhere(function ($iq) use ($date) {
//                            $iq->where('status', ScheduledState::$name)
//                                ->whereDate('scheduled_at', $date);
//                        });
                }]);
        }])->orderBy('name')->get();
    }

    /** Total paid revenue for a given dept on today. */
    public function getTodayRevenue(string $dept): float
    {
        return (float)Booking::where('dept', $dept)
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
            ->map(fn(OrBed $bed) => [
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
}
