<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HR\Enums\PayrollStatus;

class Payroll extends Model
{
    use HasUlids;

    protected $fillable = [
        'employee_id', 'month', 'year', 'base_salary', 'allowances',
        'overtime_pay', 'deductions', 'net_salary', 'status', 'paid_at', 'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'status' => PayrollStatus::class,
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
