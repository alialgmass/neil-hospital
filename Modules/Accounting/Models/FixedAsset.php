<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Enums\AssetClass;

class FixedAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'asset_class',
        'cost',
        'useful_life_months',
        'accumulated_depreciation',
        'acquired_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'asset_class' => AssetClass::class,
            'cost' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'acquired_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** Straight-line monthly depreciation, capped at the remaining undepreciated cost. */
    public function monthlyDepreciationAmount(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        $remaining = round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
        $straightLine = round((float) $this->cost / $this->useful_life_months, 2);

        return max(0.0, min($straightLine, $remaining));
    }
}
