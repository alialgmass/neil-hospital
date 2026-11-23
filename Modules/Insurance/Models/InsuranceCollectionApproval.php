<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCollectionApproval extends Model
{
    use HasUlids;

    protected $table = 'insurance_collection_approvals';

    protected $fillable = [
        'claim_id', 'recorded_by', 'recorded_at', 'amount', 'discount', 'tax', 'net',
        'reason', 'why', 'status', 'decided_by', 'decided_at', 'decision_reason',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'decided_at' => 'datetime', 'amount' => 'float', 'discount' => 'float', 'tax' => 'float', 'net' => 'float'];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
