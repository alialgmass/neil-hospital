<?php

namespace Modules\Doctor\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Enums\DebtEntryType;

class DoctorDebtSettlement extends Model
{
    use HasUlids;

    protected $fillable = [
        'doctor_id',
        'booking_id',
        'amount',
        'type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => DebtEntryType::class,
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
