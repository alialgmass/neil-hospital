<?php

namespace Modules\Clinic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clinic.write') ?? false;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'visual_acuity_od' => ['nullable', 'string', 'max:20'],
            'visual_acuity_os' => ['nullable', 'string', 'max:20'],
            'iop_od' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'iop_os' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'anterior_segment' => ['nullable', 'string', 'max:5000'],
            'posterior_segment' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
            'referral_to' => ['nullable', 'in:clinic,labs,surgery,lasik,laser'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Initial Medical Assessment
            'patient_contact' => ['nullable', 'string', 'max:30'],
            'allergies_status' => ['nullable', 'in:none,yes'],
            'allergies_specify' => ['nullable', 'string', 'max:500'],
            'current_medications' => ['nullable', 'array'],
            'current_medications.*.name' => ['nullable', 'string', 'max:150'],
            'current_medications.*.dose' => ['nullable', 'string', 'max:50'],
            'current_medications.*.route' => ['nullable', 'string', 'max:50'],
            'current_medications.*.frequency' => ['nullable', 'string', 'max:50'],
            'current_medications.*.remarks' => ['nullable', 'string', 'max:200'],
            'plan_medications' => ['nullable', 'array'],
            'plan_medications.*.name' => ['nullable', 'string', 'max:150'],
            'plan_medications.*.dose' => ['nullable', 'string', 'max:50'],
            'plan_medications.*.route' => ['nullable', 'string', 'max:50'],
            'plan_medications.*.frequency' => ['nullable', 'string', 'max:50'],
            'plan_medications.*.remarks' => ['nullable', 'string', 'max:200'],
            'visual_exam' => ['nullable', 'array'],
            'eye_exam_grid' => ['nullable', 'array'],
            'plan_education' => ['nullable', 'boolean'],
            'plan_followup' => ['nullable', 'string', 'max:1000'],

            // Initial Nursing Assessment
            'nursing_assessment' => ['nullable', 'array'],
            'nursing_history_answers' => ['nullable', 'array'],
            'nursing_history_answers.*.key' => ['required_with:nursing_history_answers', 'string'],
            'nursing_history_answers.*.answer' => ['nullable', 'in:yes,no'],
            'nursing_history_answers.*.specify' => ['nullable', 'string', 'max:300'],
            'fall_screening' => ['nullable', 'array'],
            'fall_screening.dizziness' => ['nullable', 'integer', 'in:0,5'],
            'fall_screening.pain_meds_today' => ['nullable', 'integer', 'in:0,5'],
            'fall_screening.diabetes_meds' => ['nullable', 'integer', 'in:0,5'],
            'fall_screening.gait' => ['nullable', 'integer', 'in:0,5'],
            'fall_screening.walking_aid' => ['nullable', 'integer', 'in:0,5'],
            'drops_given' => ['nullable', 'array'],
            'critical_results' => ['nullable', 'array'],
            'nursing_notes' => ['nullable', 'string', 'max:2000'],
            'nurse_signature_name' => ['nullable', 'string', 'max:150'],
            'evaluator_name' => ['nullable', 'string', 'max:150'],
            'evaluated_at' => ['nullable', 'date'],
        ];
    }
}
