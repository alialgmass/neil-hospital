<?php

namespace Modules\Clinic\Models;

use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Enums\ContactLensType;
use Modules\Clinic\Enums\ExaminationStatus;
use Modules\Clinic\Enums\GlassesUsage;
use Modules\Clinic\Enums\IopMethod;
use Modules\Doctor\Models\Doctor;

/**
 * The doctor's structured ophthalmic examination for one visit (booking).
 * Patient identity is read from the booking, never copied here; per-eye
 * findings live on {@see MedicalExaminationEye} (one OD row, one OS row).
 */
class MedicalExamination extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'doctor_id',
        'status',
        'chief_complaint',
        'complaint_duration',
        'affected_eye',
        'eye_disease_history',
        'eye_disease_notes',
        'eye_surgery_history',
        'eye_surgery_notes',
        'eye_trauma',
        'eye_trauma_notes',
        'glasses_usage',
        'contact_lenses',
        'previous_eye_medications',
        'allergies',
        'systemic_diseases',
        'history_notes',
        'iop_method',
        'assessment',
        'treatment_plan',
        'medications',
        'recommendations',
        'follow_up',
        'next_visit_date',
        'examined_at',
        'finalized_at',
        'finalized_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => ExaminationStatus::class,
        'affected_eye' => EyeSide::class,
        'glasses_usage' => GlassesUsage::class,
        'contact_lenses' => ContactLensType::class,
        'iop_method' => IopMethod::class,
        'eye_disease_history' => 'array',
        'eye_surgery_history' => 'array',
        'systemic_diseases' => 'array',
        'medications' => 'array',
        'eye_trauma' => 'boolean',
        'next_visit_date' => 'date',
        'examined_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === ExaminationStatus::Draft;
    }

    public function isFinalized(): bool
    {
        return $this->status === ExaminationStatus::Finalized;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function eyes(): HasMany
    {
        return $this->hasMany(MedicalExaminationEye::class);
    }

    public function rightEye(): HasOne
    {
        return $this->hasOne(MedicalExaminationEye::class)->where('eye', EyeSide::OD->value);
    }

    public function leftEye(): HasOne
    {
        return $this->hasOne(MedicalExaminationEye::class)->where('eye', EyeSide::OS->value);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(MedicalExaminationDiagnosis::class);
    }

    public function investigations(): HasMany
    {
        return $this->hasMany(MedicalExaminationInvestigation::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
