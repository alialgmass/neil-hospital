<?php

namespace Modules\Booking\DTOs;

readonly class BookingFilterData
{
    public function __construct(
        public ?string $date = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $dept = null,
        public ?string $status = null,
        public ?string $payStatus = null,
        public ?string $doctorId = null,
        public ?string $search = null,
        public int $perPage = 20,
        public int $page = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'] ?? null,
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            dept: $data['dept'] ?? null,
            status: $data['status'] ?? null,
            payStatus: $data['pay_status'] ?? null,
            doctorId: $data['doctor_id'] ?? null,
            search: $data['search'] ?? null,
            perPage: (int) ($data['per_page'] ?? 20),
            page: (int) ($data['page'] ?? 1),
        );
    }
}
