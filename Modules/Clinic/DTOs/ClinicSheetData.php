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
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookingId:        $data['booking_id'],
            doctorId:         $data['doctor_id'] ?? null,
            chiefComplaint:   $data['chief_complaint'] ?? null,
            visualAcuityOd:   $data['visual_acuity_od'] ?? null,
            visualAcuityOs:   $data['visual_acuity_os'] ?? null,
            iopOd:            isset($data['iop_od']) ? (float) $data['iop_od'] : null,
            iopOs:            isset($data['iop_os']) ? (float) $data['iop_os'] : null,
            anteriorSegment:  $data['anterior_segment'] ?? null,
            posteriorSegment: $data['posterior_segment'] ?? null,
            diagnosis:        $data['diagnosis'] ?? null,
            plan:             $data['plan'] ?? null,
            referralTo:       $data['referral_to'] ?? null,
            notes:            $data['notes'] ?? null,
        );
    }
}
