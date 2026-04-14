<?php

namespace Modules\Booking\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\DTOs\BookingFilterData;
use Modules\Booking\Models\Booking;

interface BookingRepositoryInterface
{
    public function paginate(BookingFilterData $filter): LengthAwarePaginator;

    public function findOrFail(string $id): Booking;

    public function findByFileNo(string $fileNo): ?Booking;

    public function create(array $data): Booking;

    public function update(string $id, array $data): Booking;

    public function updateStatus(string $id, string $status, ?string $cancelReason = null): Booking;

    public function delete(string $id): void;

    /** Count bookings per dept for a given date (dashboard use). */
    public function countByDeptForDate(string $date): array;

    /** Latest MAX sequence number for MRN generation in the given year. */
    public function maxMrnSequence(int $year): int;

    /** Check whether a file_no is already taken (for retry guard). */
    public function fileNoExists(string $fileNo): bool;
}
