<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Admin\Enums\SystemModule;

class Account extends Model
{
    use HasUlids;

    protected $fillable = ['code', 'name', 'group', 'nature', 'parent_id', 'balance', 'is_active'];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'group' => AccountGroup::class,
        'nature' => AccountNature::class,
    ];

    /** Revenue/expense accounts tied to a disable-able clinical department. */
    private const DEPT_ACCOUNTS = [
        '4010' => SystemModule::Clinic,
        '4020' => SystemModule::Labs,
        '4030' => SystemModule::Surgery,
        '4040' => SystemModule::Lasik,
        '4050' => SystemModule::Laser,
        '5020' => SystemModule::Lasik,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Exclude accounts tied to a currently disabled clinical module. */
    public function scopeModuleEnabled(Builder $query): Builder
    {
        $disabledCodes = collect(self::DEPT_ACCOUNTS)
            ->filter(fn (SystemModule $module) => ! $module->isEnabled())
            ->keys()
            ->all();

        return $disabledCodes ? $query->whereNotIn('code', $disabledCodes) : $query;
    }
}
