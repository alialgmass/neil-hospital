<?php

namespace Modules\Clinic\DTOs;

readonly class ClinicSheetData
{
    public function __construct(
        public string $bookingId,
        public ?string $doctorId = null,
        public ?string $chiefComplaint = null,
        public ?string $visualAcuityOd = null,
        public ?string $visualAcuityOs = null,
        public ?float $iopOd = null,
        public ?float $iopOs = null,
        public ?string $anteriorSegment = null,
        public ?string $posteriorSegment = null,
        public ?string $diagnosis = null,
        public ?string $plan = null,
        public ?string $referralTo = null,
        public ?string $notes = null,
        public ?string $patientContact = null,
        public ?string $allergiesStatus = null,
        public ?string $allergiesSpecify = null,
        /** @var array<int, array{name?: string, dose?: string, route?: string, frequency?: string, remarks?: string}> */
        public array $currentMedications = [],
        /** @var array<int, array{name?: string, dose?: string, route?: string, frequency?: string, remarks?: string}> */
        public array $planMedications = [],
        public array $visualExam = [],
        public array $eyeExamGrid = [],
        public ?bool $planEducation = null,
        public ?string $planFollowup = null,
        public array $nursingAssessment = [],
        /** @var array<int, array{key: string, answer?: string, specify?: string}> */
        public array $nursingHistoryAnswers = [],
        /** @var array{dizziness?: int, pain_meds_today?: int, diabetes_meds?: int, gait?: int, walking_aid?: int} */
        public array $fallScreening = [],
        public array $dropsGiven = [],
        public array $criticalResults = [],
        public ?string $nursingNotes = null,
        public ?string $nurseSignatureName = null,
        public ?string $evaluatorName = null,
        public ?string $evaluatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookingId: $data['booking_id'],
            doctorId: $data['doctor_id'] ?? null,
            chiefComplaint: $data['chief_complaint'] ?? null,
            visualAcuityOd: $data['visual_acuity_od'] ?? null,
            visualAcuityOs: $data['visual_acuity_os'] ?? null,
            iopOd: isset($data['iop_od']) ? (float) $data['iop_od'] : null,
            iopOs: isset($data['iop_os']) ? (float) $data['iop_os'] : null,
            anteriorSegment: $data['anterior_segment'] ?? null,
            posteriorSegment: $data['posterior_segment'] ?? null,
            diagnosis: $data['diagnosis'] ?? null,
            plan: $data['plan'] ?? null,
            referralTo: $data['referral_to'] ?? null,
            notes: $data['notes'] ?? null,
            patientContact: $data['patient_contact'] ?? null,
            allergiesStatus: $data['allergies_status'] ?? null,
            allergiesSpecify: $data['allergies_specify'] ?? null,
            currentMedications: $data['current_medications'] ?? [],
            planMedications: $data['plan_medications'] ?? [],
            visualExam: $data['visual_exam'] ?? [],
            eyeExamGrid: $data['eye_exam_grid'] ?? [],
            planEducation: isset($data['plan_education']) ? (bool) $data['plan_education'] : null,
            planFollowup: $data['plan_followup'] ?? null,
            nursingAssessment: $data['nursing_assessment'] ?? [],
            nursingHistoryAnswers: $data['nursing_history_answers'] ?? [],
            fallScreening: $data['fall_screening'] ?? [],
            dropsGiven: $data['drops_given'] ?? [],
            criticalResults: $data['critical_results'] ?? [],
            nursingNotes: $data['nursing_notes'] ?? null,
            nurseSignatureName: $data['nurse_signature_name'] ?? null,
            evaluatorName: $data['evaluator_name'] ?? null,
            evaluatedAt: $data['evaluated_at'] ?? null,
        );
    }
}
