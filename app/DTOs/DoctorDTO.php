<?php

namespace App\DTOs;

readonly class DoctorDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $specialty = null,
        public string $feeType = 'percentage',
        public float $feeValue = 0,
    ) {}

    public static function fromModel(object $doctor): self
    {
        return new self(
            id: $doctor->id,
            name: $doctor->name,
            specialty: $doctor->specialty,
            feeType: $doctor->fee_type,
            feeValue: (float) $doctor->fee_value,
        );
    }
}
