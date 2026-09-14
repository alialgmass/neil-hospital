<?php

namespace Modules\Doctor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\FeeType;

class Doctor extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'specialty',
        'phone',
        'fee_type',
        'fee_value',
        'dept_fees',
        'departments',
        'user_id',
        'is_active',
        'notes',
        'doctor_debt_balance',
    ];

    protected $casts = [
        'fee_value' => 'decimal:2',
        'is_active' => 'boolean',
        'fee_type' => FeeType::class,
        'dept_fees' => 'array',
        'departments' => 'array',
        'doctor_debt_balance' => 'decimal:2',
    ];

    /**
     * Add to this doctor's debt balance — the unpaid portion of a service
     * (price minus dev-treasury fee) that the doctor is liable for when a
     * patient pays nothing (see the "patient_paid = 0" business rule).
     */
    public function incurDebt(float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->increment('doctor_debt_balance', round($amount, 2));
    }

    /**
     * Settle up to $amount of this doctor's outstanding debt, returning the
     * amount actually settled (never more than the current balance or the
     * amount offered).
     */
    public function settleDebt(float $amount): float
    {
        $settled = min((float) $this->doctor_debt_balance, max(0, $amount));

        if ($settled > 0) {
            $this->decrement('doctor_debt_balance', round($settled, 2));
        }

        return round($settled, 2);
    }

    /**
     * Whether this doctor is scoped to the given department. An empty/null
     * departments list means "no restriction" — visible for every department.
     */
    public function worksInDept(string $dept): bool
    {
        return empty($this->departments) || in_array($dept, $this->departments, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DoctorShift::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DoctorPayment::class);
    }

    /**
     * Services this doctor provides, with the doctor's fee for each one
     * carried on the pivot.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'doctor_service')
            ->withPivot('fee')
            ->withTimestamps();
    }

    /**
     * Replace this doctor's per-service fee rows with the given set.
     *
     * @param  array<int, array{service_id: string, fee: numeric}>  $services
     */
    public function syncServiceFees(array $services): void
    {
        $payload = [];

        foreach ($services as $service) {
            $payload[$service['service_id']] = ['fee' => (float) $service['fee']];
        }

        $this->services()->sync($payload);
    }

    /**
     * The doctor's configured fee for a specific service, or null when no
     * per-service fee has been set for this doctor.
     */
    public function feeForService(Service|string $service): ?float
    {
        $serviceId = $service instanceof Service ? $service->id : $service;

        $pivot = $this->services()
            ->where('services.id', $serviceId)
            ->first()?->pivot;

        return $pivot ? (float) $pivot->fee : null;
    }
}
