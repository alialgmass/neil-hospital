<?php

namespace Modules\Clinic\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;

class ClinicSheet extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'doctor_id',
        'chief_complaint',
        'visual_acuity_od',
        'visual_acuity_os',
        'iop_od',
        'iop_os',
        'anterior_segment',
        'posterior_segment',
        'diagnosis',
        'plan',
        'referral_to',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'iop_od' => 'decimal:1',
        'iop_os' => 'decimal:1',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
