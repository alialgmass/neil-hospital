<?php

namespace Modules\Inventory\Models;

use App\Enums\Department;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Enums\PermitType;

class StockPermit extends Model
{
    use HasUlids;

    protected $fillable = [
        'permit_no',
        'type',
        'department',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'type' => PermitType::class,
        'department' => Department::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockPermitItem::class, 'permit_id');
    }
}
