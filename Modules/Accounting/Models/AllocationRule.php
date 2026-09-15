<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shared-expense allocation rule: which cost centers a shared expense
 * account (rent, admin salaries, ...) should be split across, and by what
 * percentage. Structure only — no posting action consumes this yet, and no
 * percentages are seeded; an administrator fills them in.
 */
class AllocationRule extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'source_account_id', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AllocationRuleTarget::class);
    }

    /** Sum of every target's percentage — callers should keep this at or under 100. */
    public function totalPercentage(): float
    {
        return (float) $this->targets()->sum('percentage');
    }

    public function isFullyAllocated(): bool
    {
        return abs($this->totalPercentage() - 100.0) < 0.01;
    }
}
