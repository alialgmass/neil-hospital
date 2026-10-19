<?php

namespace Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\HR\Enums\ContractType;
use Modules\HR\Enums\EmployeeStatus;

class Employee extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id', 'employee_no', 'name', 'national_id', 'phone', 'email',
        'dept', 'position', 'hire_date', 'base_salary', 'allowances',
        'contract_type', 'status', 'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'contract_type' => ContractType::class,
        'status' => EmployeeStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function shiftHandoversFrom(): HasMany
    {
        return $this->hasMany(ShiftHandover::class, 'from_employee_id');
    }

    public function shiftHandoversTo(): HasMany
    {
        return $this->hasMany(ShiftHandover::class, 'to_employee_id');
    }
}
