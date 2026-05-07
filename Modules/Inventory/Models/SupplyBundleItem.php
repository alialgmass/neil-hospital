<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyBundleItem extends Model
{
    protected $fillable = [
        'bundle_id',
        'inventory_item_id',
        'item_name',
        'qty',
        'unit_cost',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(SupplyBundle::class, 'bundle_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
