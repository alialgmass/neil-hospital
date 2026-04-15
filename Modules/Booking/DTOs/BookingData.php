<?php

namespace Modules\Booking\DTOs;

readonly class BookingData
{
    public function __construct(
        public string $patientName,
        public string $dept,
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
        public string $payMethod = 'cash',
        public string $payStatus = 'unpaid',
        public string $status = 'waiting',
        public ?string $visitNote = null,
        public ?int $bedNo = null,
        public ?string $eyeSide = null,
        public ?string $analysisType = null,
        public ?string $analysisNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            patientName: $data['patient_name'],
            dept: $data['dept'],
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
            payMethod: $data['pay_method'] ?? 'cash',
            payStatus: $data['pay_status'] ?? 'unpaid',
            status: $data['status'] ?? 'waiting',
            visitNote: $data['visit_note'] ?? null,
            bedNo: isset($data['bed_no']) ? (int) $data['bed_no'] : null,
            eyeSide: $data['eye_side'] ?? null,
            analysisType: $data['analysis_type'] ?? null,
            analysisNotes: $data['analysis_notes'] ?? null,
        );
    }
}
