<?php

namespace Modules\Doctor\Services;

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
        private readonly DoctorClaimsService $claimsService
    ) {}

    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Doctor::query()
            ->with([
                'services:services.id,services.name',
                'delegationServices:services.id,services.name',
            ])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getActiveDoctorsWithClaims(string $from, string $to): Collection
    {
        return Doctor::where('is_active', true)->orderBy('name')->get()->map(function ($doctor) use ($from, $to) {
            $result = $this->claimsService->calculateClaims($doctor->id, $from, $to);

            return (object) array_merge($doctor->toArray(), [
                'claim' => $result['total_claims'],
                'paid_amount' => $result['paid_amount'],
                'net_due' => $result['net_due'],
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
