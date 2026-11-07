<?php

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Enums\CallDirection;
use Modules\Booking\Enums\CallOutcome;
use Modules\Booking\Enums\CallReason;

class CallLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'direction',
        'caller_name',
        'phone',
        'file_no',
        'booking_id',
        'pre_booking_id',
        'reason',
        'outcome',
        'notes',
        'follow_up_at',
        'follow_up_done_at',
        'created_by',
    ];

    protected $casts = [
        'direction' => CallDirection::class,
        'reason' => CallReason::class,
        'outcome' => CallOutcome::class,
        'follow_up_at' => 'datetime',
        'follow_up_done_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function preBooking(): BelongsTo
    {
        return $this->belongsTo(PreBooking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
