<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Account;

class Service extends Model
{
    use HasUlids;

    protected $table = 'services';

    protected $fillable = [
        'name',
        'dept',
        'price',
        'ins_price',
        'center_type',
        'center_val',
        'center_share',
        'dr_share',
        'duration_mins',
        'status',
        'revenue_account_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'ins_price' => 'float',
            'center_val' => 'float',
            'center_share' => 'float',
            'dr_share' => 'float',
        ];
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDept($query, string $dept)
    {
        return $query->where('dept', $dept);
    }

    public function getCenterShareAttribute(): float
    {
        $price = (float) $this->attributes['price'];
        $val = (float) ($this->attributes['center_val'] ?? 0);

        if ($this->attributes['center_type'] === 'pct') {
            return round($price * $val / 100, 2);
        }

        return $val;
    }

    public function getDrShareAttribute(): float
    {
        return round((float) ($this->attributes['price'] ?? 0) - $this->getCenterShareAttribute(), 2);
    }
}
