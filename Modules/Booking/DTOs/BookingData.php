<?php

namespace Modules\Booking\DTOs;

use App\Enums\Department;
use App\Enums\EyeSide;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\States\BookingStatus;

readonly class BookingData
{
    public function __construct(
        public string $patientName,
        public Department $dept,
        public string $visitDate,
        public ?string $patientPhone = null,
        public ?int $patientAge = null,
        public ?string $nationalId = null,
        public ?string $gender = null,
        public ?string $serviceId = null,
        public ?string $serviceName = null,
        public ?string $doctorId = null,
        public ?string $insCompanyId = null,
        public ?string $visitTime = null,
        public float $price = 0,
        public float $discount = 0,
        public float $insAmount = 0,
        public float $paidAmount = 0,
        public PayMethod $payMethod = PayMethod::Cash,
        public PayStatus $payStatus = PayStatus::Unpaid,
        public string|BookingStatus $status = 'waiting',
        public ?string $visitNote = null,
        public ?int $bedId = null,
        public ?EyeSide $eyeSide = null,
        public ?string $analysisType = null,
        public ?string $analysisNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            patientName: $data['patient_name'],
            dept: $data['dept'] instanceof Department ? $data['dept'] : Department::from($data['dept']),
            visitDate: $data['visit_date'],
            patientPhone: $data['patient_phone'] ?? null,
            patientAge: isset($data['patient_age']) ? (int) $data['patient_age'] : null,
            nationalId: $data['national_id'] ?? null,
            gender: $data['gender'] ?? null,
            serviceId: $data['service_id'] ?? null,
            serviceName: $data['service_name'] ?? null,
            doctorId: $data['doctor_id'] ?? null,
            insCompanyId: $data['ins_company_id'] ?? null,
            visitTime: $data['visit_time'] ?? null,
            price: (float) ($data['price'] ?? 0),
            discount: (float) ($data['discount'] ?? 0),
            insAmount: (float) ($data['ins_amount'] ?? 0),
            paidAmount: (float) ($data['paid_amount'] ?? 0),
            payMethod: $data['pay_method'] instanceof PayMethod ? $data['pay_method'] : PayMethod::from($data['pay_method'] ?? 'cash'),
            payStatus: $data['pay_status'] instanceof PayStatus ? $data['pay_status'] : PayStatus::from($data['pay_status'] ?? 'unpaid'),
            status: $data['status'] ?? 'waiting',
            visitNote: $data['visit_note'] ?? null,
            bedId: isset($data['bed_id']) ? (int) $data['bed_id'] : null,
            eyeSide: isset($data['eye_side']) ? ($data['eye_side'] instanceof EyeSide ? $data['eye_side'] : EyeSide::from($data['eye_side'])) : null,
            analysisType: $data['analysis_type'] ?? null,
            analysisNotes: $data['analysis_notes'] ?? null,
        );
    }
}
