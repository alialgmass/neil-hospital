<?php

namespace Modules\Doctor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationRole;
use Modules\Doctor\Enums\DelegationStatus;

class BookingDoctorDelegation extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'doctor_id',
        'role',
        'service_id',
        'service_name',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'role' => DelegationRole::class,
        'status' => DelegationStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
