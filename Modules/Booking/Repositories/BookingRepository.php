<?php

namespace Modules\Booking\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Booking);
    }

    public function paginate(BookingFilterData $filter): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['doctor:id,name', 'insuranceCompany:id,name'])
            ->latest('visit_date');

        if ($filter->date) {
            $query->whereDate('visit_date', $filter->date);
        } elseif ($filter->dateFrom || $filter->dateTo) {
            if ($filter->dateFrom) {
                $query->whereDate('visit_date', '>=', $filter->dateFrom);
            }
            if ($filter->dateTo) {
                $query->whereDate('visit_date', '<=', $filter->dateTo);
            }
        }

        if ($filter->dept) {
            $query->where('dept', $filter->dept);
        }

        if ($filter->status) {
            $query->where('status', $filter->status);
        }

        if ($filter->payStatus) {
            $query->where('pay_status', $filter->payStatus);
        }

        if ($filter->doctorId) {
            $query->where('doctor_id', $filter->doctorId);
        }

        if ($filter->search) {
            $query->where(function ($q) use ($filter) {
                $q->where('patient_name', 'like', "%{$filter->search}%")
                  ->orWhere('file_no', 'like', "%{$filter->search}%")
                  ->orWhere('patient_phone', 'like', "%{$filter->search}%");
            });
        }

        return $query->paginate($filter->perPage);
    }

    public function findOrFail(string $id): Booking
    {
        /** @var Booking */
        return Booking::with(['doctor', 'service', 'insuranceCompany', 'createdBy'])->findOrFail($id);
    }

    public function findByFileNo(string $fileNo): ?Booking
    {
        return Booking::where('file_no', $fileNo)->first();
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function update(string $id, array $data): Booking
    {
        $booking = $this->findOrFail($id);
        $booking->update($data);

        return $booking->fresh();
    }

    public function updateStatus(string $id, string $status, ?string $cancelReason = null): Booking
    {
        $booking = $this->findOrFail($id);
        $update = ['status' => $status];
        if ($cancelReason !== null) {
            $update['cancel_reason'] = $cancelReason;
        }
        $booking->update($update);

        return $booking->fresh();
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id)->delete();
    }

    public function countByDeptForDate(string $date): array
    {
        return Booking::query()
            ->whereDate('visit_date', $date)
            ->selectRaw('dept, count(*) as total')
            ->groupBy('dept')
            ->pluck('total', 'dept')
            ->all();
    }

    public function maxMrnSequence(int $year): int
    {
        $prefix = "MRN-{$year}-";

        $max = Booking::where('file_no', 'like', "{$prefix}%")
            ->selectRaw("MAX(CAST(SUBSTRING(file_no, ?) AS UNSIGNED)) as seq", [strlen($prefix) + 1])
            ->value('seq');

        return (int) $max;
    }

    public function fileNoExists(string $fileNo): bool
    {
        return Booking::where('file_no', $fileNo)->exists();
    }
}
