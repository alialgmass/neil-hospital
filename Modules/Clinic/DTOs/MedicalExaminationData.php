<?php

namespace Modules\Clinic\DTOs;

use App\Enums\EyeSide;
use Illuminate\Support\Arr;
use Modules\Clinic\Models\MedicalExaminationEye;

readonly class MedicalExaminationData
{
    /** Scalar/JSON columns of medical_examinations filled straight from the form. */
    public const ATTRIBUTE_FIELDS = [
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
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, mixed>>  $eyes  keyed by EyeSide value (OD / OS)
     * @param  array<int, array{diagnosis_id?: ?string, name?: ?string, eye?: ?string, notes?: ?string}>  $diagnoses
     * @param  array<int, array{service_id: string, eye?: ?string, notes?: ?string}>  $investigations
     */
    public function __construct(
        public ?string $doctorId,
        public array $attributes,
        public array $eyes,
        public array $diagnoses,
        public array $investigations,
        public bool $finalize = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated request payload
     */
    public static function fromArray(array $data): self
    {
        $attributes = [];

        foreach (self::ATTRIBUTE_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        $attributes['medications'] = array_values(array_filter(
            $data['medications'] ?? [],
            fn (array $row) => trim((string) ($row['name'] ?? '')) !== '',
        ));

        $eyes = [];

        foreach ([EyeSide::OD->value, EyeSide::OS->value] as $eye) {
            $eyes[$eye] = Arr::only($data['eyes'][$eye] ?? [], MedicalExaminationEye::FIELDS)
                + array_fill_keys(MedicalExaminationEye::FIELDS, null);
        }

        return new self(
            doctorId: $data['doctor_id'] ?? null,
            attributes: $attributes,
            eyes: $eyes,
            diagnoses: array_values($data['diagnoses'] ?? []),
            investigations: array_values($data['investigations'] ?? []),
            finalize: (bool) ($data['finalize'] ?? false),
        );
    }
}
