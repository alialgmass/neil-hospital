<?php

namespace Modules\Surgery\DTOs;

use App\Enums\Department;
use App\Enums\EyeSide;
use Modules\Surgery\Enums\Anaesthesia;
use Modules\Surgery\States\SurgeryStatus;

readonly class SurgeryData
{
    public function __construct(
        public string $bookingId,
        public Department $dept,
        public ?int $orBedId = null,
        public ?int $bedNo = null,
        public ?string $surgeonId = null,
        public ?EyeSide $eye = null,
        public ?string $procedure = null,
        public ?Anaesthesia $anaesthesia = null,
        public string|SurgeryStatus $status = 'scheduled',
        public ?string $preOpNotes = null,
        public ?string $scheduledAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookingId: $data['booking_id'],
            dept: $data['dept'] instanceof Department ? $data['dept'] : Department::from($data['dept'] ?? 'surgery'),
            orBedId: isset($data['or_bed_id']) ? (int) $data['or_bed_id'] : null,
            bedNo: isset($data['bed_no']) ? (int) $data['bed_no'] : null,
            surgeonId: $data['surgeon_id'] ?? null,
            eye: isset($data['eye']) ? ($data['eye'] instanceof EyeSide ? $data['eye'] : EyeSide::from($data['eye'])) : null,
            procedure: $data['procedure'] ?? null,
            anaesthesia: isset($data['anaesthesia']) ? ($data['anaesthesia'] instanceof Anaesthesia ? $data['anaesthesia'] : Anaesthesia::from($data['anaesthesia'])) : null,
            status: $data['status'] ?? 'scheduled',
            preOpNotes: $data['pre_op_notes'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
        );
    }
}
