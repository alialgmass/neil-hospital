<?php

namespace Modules\Clinic\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;

class ClinicSheet extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id',
        'doctor_id',
        'chief_complaint',
        'visual_acuity_od',
        'visual_acuity_os',
        'iop_od',
        'iop_os',
        'anterior_segment',
        'posterior_segment',
        'diagnosis',
        'plan',
        'referral_to',
        'notes',
        'recorded_at',
        'patient_contact',
        'allergies_status',
        'allergies_specify',
        'current_medications',
        'plan_medications',
        'visual_exam',
        'eye_exam_grid',
        'plan_education',
        'plan_followup',
        'nursing_assessment',
        'nursing_history_answers',
        'fall_screening',
        'fall_screening_score',
        'drops_given',
        'critical_results',
        'nursing_notes',
        'nurse_signature_name',
        'evaluator_name',
        'evaluated_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'iop_od' => 'decimal:1',
        'iop_os' => 'decimal:1',
        'allergies_status' => 'string',
        'current_medications' => 'array',
        'plan_medications' => 'array',
        'visual_exam' => 'array',
        'eye_exam_grid' => 'array',
        'plan_education' => 'boolean',
        'nursing_assessment' => 'array',
        'nursing_history_answers' => 'array',
        'fall_screening' => 'array',
        'fall_screening_score' => 'integer',
        'drops_given' => 'array',
        'critical_results' => 'array',
        'evaluated_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
