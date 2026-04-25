<?php

namespace Modules\Doctor\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Booking\States\ConfirmedStatus;
use Modules\Booking\States\InProgressStatus;
use Modules\Booking\States\WaitingStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class DoctorService
{
    public function __construct(
        private readonly ClaimCalculator $claimCalculator
    ) {}

    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Doctor::query()
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getActiveDoctorsWithClaims(string $from, string $to): Collection
    {
        return Doctor::where('is_active', true)->get()->map(function ($doctor) use ($from, $to) {
            $calc = $this->claimCalculator->calculate($doctor, Carbon::parse($from), Carbon::parse($to));

            return (object) array_merge($doctor->toArray(), [
                'claim' => $calc['stats']['total_claim'],
            ]);
        });
    }

    public function getPendingBookingsForShift(string $doctorId, string $date): Collection
    {
        return Booking::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('visit_date', $date)
            ->whereIn('status', [ConfirmedStatus::class, WaitingStatus::class, InProgressStatus::class])
            ->get(['id', 'patient_name', 'dept', 'status']);
    }

    public function payments(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return DoctorPayment::query()
            ->with('doctor:id,name')
            ->when($filters['doctor_id'] ?? null, fn ($q, $v) => $q->where('doctor_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->orderByDesc('paid_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allActive(): Collection
    {
        return Doctor::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
