<?php

namespace Modules\Doctor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorShift extends Model
{
    use HasUlids;

    protected $table = 'doctor_shifts';

    protected $fillable = [
        'doctor_id', 'shift_date', 'dept', 'start_time', 'end_time',
        'cases_count', 'total_revenue', 'doctor_share', 'center_share',
        'handover_notes', 'is_closed', 'closed_by',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'total_revenue' => 'decimal:2',
        'doctor_share' => 'decimal:2',
        'center_share' => 'decimal:2',
        'is_closed' => 'boolean',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
