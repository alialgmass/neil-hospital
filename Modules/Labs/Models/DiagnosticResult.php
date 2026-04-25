<?php

namespace Modules\Labs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;

class DiagnosticResult extends Model
{
    use HasUlids;

    protected $table = 'diagnostic_results';

    protected $fillable = [
        'booking_id', 'test_name', 'eye', 'result_text',
        'result_values', 'image_path', 'technician_id', 'doctor_notes', 'recorded_at',
    ];

    protected $casts = [
        'result_values' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
