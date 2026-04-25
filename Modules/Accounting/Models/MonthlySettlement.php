<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySettlement extends Model
{
    protected $fillable = [
        'month_period',
        'total_revenue',
        'total_expenses',
        'total_doctor_claims',
        'net_surplus_deficit',
        'is_locked',
        'locked_at',
        'locked_by',
        'notes',
    ];

    protected $casts = [
        'total_revenue' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'total_doctor_claims' => 'decimal:2',
        'net_surplus_deficit' => 'decimal:2',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
