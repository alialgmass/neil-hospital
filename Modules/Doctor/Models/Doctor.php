<?php

namespace Modules\Doctor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Booking\Models\Booking;
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
    ];

    protected $casts = [
        'fee_value' => 'decimal:2',
        'is_active' => 'boolean',
        'fee_type' => FeeType::class,
        'dept_fees' => 'array',
        'departments' => 'array',
    ];

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
}
