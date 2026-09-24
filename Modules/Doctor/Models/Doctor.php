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
        'is_anesthesiologist',
        'notes',
        'doctor_debt_balance',
    ];

    protected $casts = [
        'fee_value' => 'decimal:2',
        'is_active' => 'boolean',
        'is_anesthesiologist' => 'boolean',
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
     * Settle debt out of a specific booking's computed share and record how
     * much — doctor_debt_balance is a single running scalar with no history,
     * so without this ledger row DoctorClaimsService has no way to know that
     * part of this booking's share never became newly payable and would
     * keep reporting it as still "مستحق" forever. See DoctorDebtSettlement.
     */
    public function settleDebtForBooking(string $bookingId, float $amount): float
    {
        $settled = $this->settleDebt($amount);

        if ($settled > 0) {
            $this->debtSettlements()->create([
                'booking_id' => $bookingId,
                'amount' => $settled,
            ]);
        }

        return $settled;
    }

    public function debtSettlements(): HasMany
    {
        return $this->hasMany(DoctorDebtSettlement::class);
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

    /**
     * Services this doctor can be delegated/assigned-as-anesthesiologist for,
     * with this doctor's fee for each one carried on the pivot. Deliberately
     * separate from services()/doctor_service (insurance/contract fees) — a
     * delegation price for a service is independent of this doctor's normal
     * fee for that same service.
     */
    public function delegationServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'doctor_delegation_fees')
            ->withPivot('fee')
            ->withTimestamps();
    }

    /**
     * Replace this doctor's delegation/anesthesia fee rows with the given set.
     *
     * @param  array<int, array{service_id: string, fee: numeric}>  $services
     */
    public function syncDelegationFees(array $services): void
    {
        $payload = [];

        foreach ($services as $service) {
            $payload[$service['service_id']] = ['fee' => (float) $service['fee']];
        }

        $this->delegationServices()->sync($payload);
    }

    /**
     * The doctor's delegation/anesthesia fee for a specific service, or null
     * when none has been set for this doctor.
     */
    public function delegationFeeForService(Service|string $service): ?float
    {
        $serviceId = $service instanceof Service ? $service->id : $service;

        $pivot = $this->delegationServices()
            ->where('services.id', $serviceId)
            ->first()?->pivot;

        return $pivot ? (float) $pivot->fee : null;
    }
}
