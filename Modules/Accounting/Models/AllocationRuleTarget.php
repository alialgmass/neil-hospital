<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Exceptions\AccountingException;

class AllocationRuleTarget extends Model
{
    use HasUlids;

    protected $fillable = ['allocation_rule_id', 'cost_center', 'percentage'];

    protected function casts(): array
    {
        return [
            'cost_center' => CostCenter::class,
            'percentage' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // A rule's targets must never allocate more than 100% of the
        // shared expense in total — guard it here so every creation path
        // (direct, factory, seeder) is covered, not just one controller.
        static::saving(function (AllocationRuleTarget $target) {
            $existing = static::where('allocation_rule_id', $target->allocation_rule_id)
                ->when($target->exists, fn ($q) => $q->whereKeyNot($target->id))
                ->sum('percentage');

            if ((float) $existing + (float) $target->percentage > 100.0001) {
                throw new AccountingException('Allocation rule targets cannot total more than 100%.');
            }
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AllocationRule::class, 'allocation_rule_id');
    }
}
