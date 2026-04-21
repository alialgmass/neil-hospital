<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Inventory\Models\Service;

class InsuranceClaim extends Model
{
    protected $table = 'insurance_claims';

    protected $fillable = [
        'booking_id',
        'insurance_company_id',
        'service_id',
        'patient_name',
        'file_no',
        'service_name',
        'invoice_amount',
        'discount',
        'patient_share',
        'insurance_share',
        'approved_amount',
        'paid_amount',
        'status',
        'service_date',
        'claim_date',
        'submission_date',
        'approval_date',
        'payment_date',
        'claim_reference',
        'rejection_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_amount' => 'float',
            'discount' => 'float',
            'patient_share' => 'float',
            'insurance_share' => 'float',
            'approved_amount' => 'float',
            'paid_amount' => 'float',
            'service_date' => 'date',
            'claim_date' => 'date',
            'submission_date' => 'date',
            'approval_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
