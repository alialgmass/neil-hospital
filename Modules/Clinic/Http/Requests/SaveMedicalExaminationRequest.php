<?php

namespace Modules\Clinic\Http\Requests;

use App\Enums\Department;
use App\Enums\EyeSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Enums\ContactLensType;
use Modules\Clinic\Enums\EyeDisease;
use Modules\Clinic\Enums\EyeSurgery;
use Modules\Clinic\Enums\GlassesUsage;
use Modules\Clinic\Enums\IopMethod;
use Modules\Clinic\Enums\SystemicDisease;
use Modules\Clinic\Models\MedicalExamination;

/**
 * Validates both creating (POST examinations/booking/{booking}) and updating
 * (PUT examinations/{examination}) an examination. Every clinical field is
 * optional so the doctor can save a draft progressively; the chief complaint
 * and at least one diagnosis become mandatory only when finalizing.
 */
class SaveMedicalExaminationRequest extends FormRequest
{
    /** Departments whose services can be requested as investigations. */
    public const INVESTIGATION_DEPARTMENTS = [Department::Labs, Department::Pentacam];

    public function authorize(): bool
    {
        $examination = $this->route('examination');

        if ($examination instanceof MedicalExamination) {
            return $this->user()?->can('update', $examination) ?? false;
        }

        return $this->user()?->can('examinations.create') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $finalizing = $this->boolean('finalize');
        $text = ['nullable', 'string', 'max:2000'];
        $finding = ['nullable', 'string', 'max:255'];
        $acuity = ['nullable', 'string', 'max:20'];

        $rules = [
            'finalize' => ['nullable', 'boolean'],
            'doctor_id' => ['nullable', Rule::exists('doctors', 'id')],

            'chief_complaint' => [Rule::requiredIf($finalizing), 'nullable', 'string', 'max:2000'],
            'complaint_duration' => ['nullable', 'string', 'max:100'],
            'affected_eye' => ['nullable', Rule::enum(EyeSide::class)],

            'eye_disease_history' => ['nullable', 'array'],
            'eye_disease_history.*' => ['distinct', Rule::enum(EyeDisease::class)],
            'eye_disease_notes' => $text,
            'eye_surgery_history' => ['nullable', 'array'],
            'eye_surgery_history.*' => ['distinct', Rule::enum(EyeSurgery::class)],
            'eye_surgery_notes' => $text,
            'eye_trauma' => ['nullable', 'boolean'],
            'eye_trauma_notes' => $text,
            'glasses_usage' => ['nullable', Rule::enum(GlassesUsage::class)],
            'contact_lenses' => ['nullable', Rule::enum(ContactLensType::class)],
            'previous_eye_medications' => $text,
            'allergies' => $text,
            'systemic_diseases' => ['nullable', 'array'],
            'systemic_diseases.*' => ['distinct', Rule::enum(SystemicDisease::class)],
            'history_notes' => $text,

            'iop_method' => ['nullable', Rule::enum(IopMethod::class)],

            'assessment' => ['nullable', 'string', 'max:5000'],
            'treatment_plan' => ['nullable', 'string', 'max:5000'],
            'recommendations' => $text,
            'follow_up' => ['nullable', 'string', 'max:255'],
            'next_visit_date' => ['nullable', 'date', 'after_or_equal:'.$this->visitDate()],

            'medications' => ['nullable', 'array', 'max:30'],
            'medications.*.name' => ['nullable', 'string', 'max:150'],
            'medications.*.dose' => ['nullable', 'string', 'max:50'],
            'medications.*.route' => ['nullable', 'string', 'max:50'],
            'medications.*.frequency' => ['nullable', 'string', 'max:50'],
            'medications.*.remarks' => ['nullable', 'string', 'max:200'],

            'eyes' => ['nullable', 'array:'.EyeSide::OD->value.','.EyeSide::OS->value],

            'diagnoses' => array_filter([Rule::requiredIf($finalizing), 'nullable', 'array', $finalizing ? 'min:1' : null, 'max:20']),
            'diagnoses.*.diagnosis_id' => ['nullable', 'required_without:diagnoses.*.name', Rule::exists('diagnoses', 'id')],
            'diagnoses.*.name' => ['nullable', 'string', 'max:150'],
            'diagnoses.*.eye' => ['nullable', Rule::enum(EyeSide::class)],
            'diagnoses.*.notes' => ['nullable', 'string', 'max:500'],

            'investigations' => ['nullable', 'array', 'max:20'],
            'investigations.*.service_id' => [
                'required',
                'distinct',
                Rule::exists('services', 'id')->whereIn(
                    'dept',
                    array_map(fn (Department $dept) => $dept->value, self::INVESTIGATION_DEPARTMENTS),
                ),
            ],
            'investigations.*.eye' => ['nullable', Rule::enum(EyeSide::class)],
            'investigations.*.notes' => ['nullable', 'string', 'max:500'],
        ];

        foreach ([EyeSide::OD->value, EyeSide::OS->value] as $eye) {
            $prefix = "eyes.{$eye}.";

            $rules += [
                $prefix.'ucva' => $acuity,
                $prefix.'cva' => $acuity,
                $prefix.'near_vision' => $acuity,
                $prefix.'bcva' => $acuity,
                $prefix.'sphere' => ['nullable', 'numeric', 'between:-30,30', 'multiple_of:0.25'],
                $prefix.'cylinder' => ['nullable', 'numeric', 'between:-15,15', 'multiple_of:0.25'],
                $prefix.'axis' => [
                    Rule::requiredIf(fn () => (float) $this->input($prefix.'cylinder', 0) != 0.0),
                    'nullable',
                    'integer',
                    'between:0,180',
                ],
                $prefix.'add_power' => ['nullable', 'numeric', 'between:0,4', 'multiple_of:0.25'],
                $prefix.'eyelids' => $finding,
                $prefix.'conjunctiva' => $finding,
                $prefix.'sclera' => $finding,
                $prefix.'cornea' => $finding,
                $prefix.'anterior_chamber' => $finding,
                $prefix.'iris' => $finding,
                $prefix.'pupil' => $finding,
                $prefix.'lens' => $finding,
                $prefix.'iop' => ['nullable', 'numeric', 'between:0,80'],
                $prefix.'optic_disc' => $finding,
                $prefix.'cd_ratio' => ['nullable', 'numeric', 'between:0,1'],
                $prefix.'macula' => $finding,
                $prefix.'retina' => $finding,
                $prefix.'vessels' => $finding,
                $prefix.'vitreous' => $finding,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'chief_complaint' => 'الشكوى الرئيسية',
            'diagnoses' => 'التشخيص',
            'next_visit_date' => 'موعد الزيارة القادمة',
            'investigations.*.service_id' => 'الفحص المطلوب',
            'diagnoses.*.diagnosis_id' => 'التشخيص',
        ];

        $labels = [
            'sphere' => 'Sphere', 'cylinder' => 'Cylinder', 'axis' => 'Axis', 'add_power' => 'Add',
            'iop' => 'IOP', 'cd_ratio' => 'C/D Ratio',
        ];

        foreach ([EyeSide::OD->value, EyeSide::OS->value] as $eye) {
            foreach ($labels as $field => $label) {
                $attributes["eyes.{$eye}.{$field}"] = "{$label} ({$eye})";
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chief_complaint.required' => 'الشكوى الرئيسية مطلوبة لاعتماد الفحص.',
            'diagnoses.required' => 'يجب إضافة تشخيص واحد على الأقل لاعتماد الفحص.',
            'diagnoses.min' => 'يجب إضافة تشخيص واحد على الأقل لاعتماد الفحص.',
            'eyes.*.axis.required' => 'المحور (Axis) مطلوب عند إدخال قيمة Cylinder.',
            'eyes.*.*.multiple_of' => 'القيمة يجب أن تكون بمضاعفات 0.25.',
            'next_visit_date.after_or_equal' => 'موعد الزيارة القادمة لا يمكن أن يكون قبل تاريخ الزيارة الحالية.',
        ];
    }

    private function visitDate(): string
    {
        $examination = $this->route('examination');
        $booking = $examination instanceof MedicalExamination ? $examination->booking : $this->route('booking');

        return $booking instanceof Booking && $booking->visit_date
            ? $booking->visit_date->toDateString()
            : today()->toDateString();
    }
}
