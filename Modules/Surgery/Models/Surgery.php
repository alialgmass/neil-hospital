<?php

namespace Modules\Surgery\Models;

use App\Enums\Department;
use App\Enums\EyeSide;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Enums\Anaesthesia;
use Modules\Surgery\States\SurgeryStatus;
use Spatie\ModelStates\HasStates;

class Surgery extends Model
{
    use HasStates;
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'or_bed_id',
        'bed_no',
        'surgeon_id',
        'dept',
        'eye',
        'procedure',
        'anaesthesia',
        'status',
        'pre_op_notes',
        'op_report',
        'post_op_notes',
        'complications',
        'scheduled_at',
        'started_at',
        'ended_at',
        'supplies_used',
        'supply_total',
    ];

    protected $casts = [
        'supplies_used' => 'array',
        'supply_total' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'dept' => Department::class,
        'eye' => EyeSide::class,
        'anaesthesia' => Anaesthesia::class,
        'status' => SurgeryStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'surgeon_id');
    }

    public function orBed(): BelongsTo
    {
        return $this->belongsTo(OrBed::class, 'or_bed_id');
    }
}
