<?php

namespace Modules\Booking\Models;

use App\Enums\Department;
use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\States\BookingStatus;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Surgery\Models\Surgery;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

class Booking extends Model implements HasMedia
{
    use HasStates;
    use HasUlids;
    use InteractsWithMedia;

    protected $fillable = [
        'file_no',
        'patient_name',
        'patient_phone',
        'patient_age',
        'national_id',
        'gender',
        'dept',
        'service_name',
        'service_id',
        'doctor_id',
        'ins_company_id',
        'visit_date',
        'visit_time',
        'price',
        'discount',
        'ins_amount',
        'paid_amount',
        'pay_method',
        'pay_status',
        'status',
        'cancel_reason',
        'visit_note',
        'bed_no',
        'eye_side',
        'analysis_type',
        'analysis_notes',
        'created_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'ins_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'patient_age' => 'integer',
        'dept' => Department::class,
        'pay_method' => PayMethod::class,
        'pay_status' => PayStatus::class,
        'status' => BookingStatus::class,
        'eye_side' => EyeSide::class,
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'ins_company_id');
    }

    public function insuranceClaim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class, 'booking_id');
    }

    public function clinicSheet(): HasOne
    {
        return $this->hasOne(ClinicSheet::class);
    }

    public function diagnosticResults(): HasMany
    {
        return $this->hasMany(DiagnosticResult::class);
    }

    public function surgery(): HasOne
    {
        return $this->hasOne(Surgery::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archive-files');
    }

    public function getNetAmountAttribute(): float
    {
        return max(0, (float) $this->price - (float) $this->discount);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->getNetAmountAttribute() - (float) $this->paid_amount);
    }
}
