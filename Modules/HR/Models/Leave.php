<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Enums\LeaveType;

class Leave extends Model
{
    use HasUlids;

    protected $table = 'hr_leaves';

    protected $fillable = [
        'employee_id', 'type', 'from_date', 'to_date', 'days',
        'reason', 'status', 'approved_by', 'notes',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'type' => LeaveType::class,
        'status' => LeaveStatus::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
