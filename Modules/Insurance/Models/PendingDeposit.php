<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Enums\DepositDecision;

class PendingDeposit extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'pending_deposits';

    protected $fillable = [
        'booking_id',
        'insurance_company_id',
        'amount',
        'paid_on',
        'decision',
        'refunded',
        'converted',
        'claim_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_on' => 'date',
            'decision' => DepositDecision::class,
            'refunded' => 'boolean',
            'converted' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id');
    }
}
