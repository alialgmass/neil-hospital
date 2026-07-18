<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\ItemUnit;

class InventoryItem extends Model
{
    use HasUlids;

    protected $table = 'inventory';

    protected $fillable = [
        'name', 'code', 'category', 'unit', 'quantity', 'min_quantity',
        'unit_cost', 'sell_price', 'supplier_id', 'expiry_date', 'location', 'notes',
    ];

    protected $appends = ['unit_label', 'category_label'];

    protected $casts = [
        'quantity' => 'decimal:2',
        'min_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'expiry_date' => 'date',
        'category' => ItemCategory::class,
        'unit' => ItemUnit::class,
    ];

    public function getUnitLabelAttribute(): string
    {
        return $this->unit?->label() ?? '';
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->category?->label() ?? '';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity && $this->min_quantity > 0;
    }
}
