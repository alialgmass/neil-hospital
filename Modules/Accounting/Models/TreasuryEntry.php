<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Booking\Models\Booking;

class TreasuryEntry extends Model
{
    use HasUlids;

    protected $fillable = [
        'type', 'description', 'amount', 'date',
        'reference_no', 'beneficiary', 'account_id',
        'source', 'booking_id', 'created_by',
        'reversal_of_id', 'reversed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'type' => TreasuryType::class,
        'reversed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The entry this one reverses, if any. */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
