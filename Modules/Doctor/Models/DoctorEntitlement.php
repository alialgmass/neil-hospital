<?php

namespace Modules\Doctor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\EntitlementSource;
use Modules\Doctor\Enums\EntitlementStatus;

class DoctorEntitlement extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'doctor_id',
        'service_id',
        'amount',
        'source',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'source' => EntitlementSource::class,
        'status' => EntitlementStatus::class,
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
