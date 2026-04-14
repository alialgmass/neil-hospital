<?php

namespace Modules\Clinic\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Clinic\Models\ClinicSheet;

interface ClinicSheetRepositoryInterface
{
    public function findByBooking(string $bookingId): ?ClinicSheet;

    public function createOrUpdate(string $bookingId, array $data): ClinicSheet;

    /** All clinic sheets for a patient identified by file_no prefix (MRN). */
    public function patientHistory(string $patientName, ?string $phone = null): Collection;
}
