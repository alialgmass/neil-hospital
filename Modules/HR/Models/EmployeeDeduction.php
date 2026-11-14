<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Enums\DeductionType;

class EmployeeDeduction extends Model
{
    use HasUlids;

    protected $fillable = [
        'employee_id', 'deduction_date', 'type', 'days', 'daily_rate',
        'amount', 'reason', 'created_by',
    ];

    protected $casts = [
        'deduction_date' => 'date',
        'type' => DeductionType::class,
        'days' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
