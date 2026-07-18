<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Account;

class Service extends Model
{
    use HasUlids;

    protected $fillable = [
        'name', 'dept', 'price', 'ins_price',
        'center_type', 'center_val', 'center_share', 'dr_share',
        'duration_mins', 'status', 'revenue_account_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ins_price' => 'decimal:2',
        'center_val' => 'decimal:2',
        'center_share' => 'decimal:2',
        'dr_share' => 'decimal:2',
    ];

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForDept($query, string $dept)
    {
        return $query->where('dept', $dept);
    }
}
