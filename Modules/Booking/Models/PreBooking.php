<?php

namespace Modules\Booking\Models;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Enums\PreBookingStatus;
use Modules\Doctor\Models\Doctor;

/**
 * A preliminary booking taken by the call center. Reception confirms it by
 * creating a real booking from it, which marks it Converted.
 */
class PreBooking extends Model
{
    use HasUlids;

    protected $fillable = [
        'patient_name',
        'patient_phone',
        'national_id',
        'file_no',
        'dept',
        'service_id',
        'doctor_id',
        'ins_company_id',
        'preferred_date',
        'preferred_time',
        'notes',
        'status',
        'booking_id',
        'created_by',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'dept' => Department::class,
        'status' => PreBookingStatus::class,
        'preferred_date' => 'date:Y-m-d',
        'handled_at' => 'datetime',
    ];

    /** @param  Builder<PreBooking>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PreBookingStatus::Pending);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'ins_company_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
