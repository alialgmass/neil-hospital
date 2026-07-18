<?php

namespace Modules\Doctor\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorPayment extends Model
{
    use HasUlids;

    protected $table = 'dr_payments';

    protected $fillable = [
        'doctor_id', 'amount', 'period_from', 'period_to',
        'paid_at', 'method', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'paid_at' => 'date',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
