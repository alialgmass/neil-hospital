<?php

namespace Modules\Booking\Repositories;

use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Admin\Enums\SystemModule;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\BookingStatus;

class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Booking);
    }

    public function filterAndPaginate(BookingFilterData $filter): LengthAwarePaginator
    {
        return $this->filterQuery($filter)->paginate($filter->perPage);
    }

    /**
     * All bookings matching the given filters (no pagination) — used by Excel export.
     *
     * @return Collection<int, Booking>
     */
    public function filteredAll(BookingFilterData $filter): Collection
    {
        return $this->filterQuery($filter)->get();
    }

    private function filterQuery(BookingFilterData $filter): Builder
    {
        $query = Booking::query()
            ->with(['doctor:id,name', 'insuranceCompany:id,name', 'surgery:id,booking_id,or_bed_id'])
            ->whereIn('dept', SystemModule::enabledDeptValues())
            ->whereIn('status', BookingStatus::visibleStatusNames())
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');

        // Hide completed & cancelled from the default listing; user can see them via the status filter
        if (! $filter->status) {
            $query->whereNotIn('status', ['completed', 'completed_electronic', 'cancelled']);
        }

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

        return $query;
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
            ->whereIn('dept', SystemModule::enabledDeptValues())
            ->selectRaw('dept, count(*) as total')
            ->groupBy('dept')
            ->pluck('total', 'dept')
            ->all();
    }

    public function maxFileSequence(): int
    {
        // Supports new format P-{seq}-{last3} and legacy MRN-YYYY-{seq}.
        // Parsed in PHP (rather than DB-specific SUBSTRING_INDEX/REGEXP SQL)
        // so this works identically across MySQL and SQLite.
        $max = Booking::where('file_no', 'like', 'P-%')
            ->pluck('file_no')
            ->map(fn (string $fileNo) => preg_match('/^P-(\d+)-/', $fileNo, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        if ($max > 0) {
            return $max;
        }

        // Fall back to legacy MRN sequence so numbering continues from where it left off
        $year = now()->year;
        $prefix = "MRN-{$year}-";

        return (int) Booking::where('file_no', 'like', "{$prefix}%")
            ->pluck('file_no')
            ->map(fn (string $fileNo) => (int) substr($fileNo, strlen($prefix)))
            ->max() ?? 0;
    }

    public function fileNoExists(string $fileNo): bool
    {
        return Booking::where('file_no', $fileNo)->exists();
    }
}
