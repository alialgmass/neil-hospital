<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Enums\HandoverStatus;

class ShiftHandover extends Model
{
    use HasUlids;

    protected $fillable = [
        'shift_id', 'from_employee_id', 'to_employee_id',
        'handover_date', 'handover_time', 'cash_amount', 'notes', 'status',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'cash_amount' => 'decimal:2',
        'status' => HandoverStatus::class,
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }
}
