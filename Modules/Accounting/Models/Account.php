<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Admin\Enums\SystemModule;

class Account extends Model
{
    use HasUlids;

    protected $fillable = ['code', 'name', 'group', 'nature', 'parent_id', 'balance', 'is_active', 'is_postable'];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_postable' => 'boolean',
        'group' => AccountGroup::class,
        'nature' => AccountNature::class,
    ];

    /**
     * Revenue/expense accounts tied to a disable-able clinical department.
     * Sourced from AccountCode::deptRevenueMap() (single source of truth,
     * shared with AutoPostBookingPaymentAction) plus the Lasik supplies-cost
     * account, which also has no reason to show up once Lasik is disabled.
     */
    private static function deptAccounts(): array
    {
        $map = [];

        foreach (AccountCode::deptRevenueMap() as $deptValue => $accountCode) {
            $module = match ($deptValue) {
                'clinic' => SystemModule::Clinic,
                'labs' => SystemModule::Labs,
                'surgery' => SystemModule::Surgery,
                'lasik' => SystemModule::Lasik,
                'laser' => SystemModule::Laser,
                default => null,
            };

            if ($module) {
                $map[$accountCode->value] = $module;
            }
        }

        $map[AccountCode::LASIK_SUPPLIES_COST->value] = SystemModule::Lasik;

        return $map;
    }

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
        $disabledCodes = collect(self::deptAccounts())
            ->filter(fn (SystemModule $module) => ! $module->isEnabled())
            ->keys()
            ->all();

        return $disabledCodes ? $query->whereNotIn('code', $disabledCodes) : $query;
    }
}
