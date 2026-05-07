<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyBundle extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'code',
        'dept',
        'price',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyBundleItem::class, 'bundle_id');
    }
}
