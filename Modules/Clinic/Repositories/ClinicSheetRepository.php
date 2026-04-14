<?php

namespace Modules\Clinic\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;

class ClinicSheetRepository implements ClinicSheetRepositoryInterface
{
    public function findByBooking(string $bookingId): ?ClinicSheet
    {
        return ClinicSheet::where('booking_id', $bookingId)->first();
    }

    public function createOrUpdate(string $bookingId, array $data): ClinicSheet
    {
        return ClinicSheet::updateOrCreate(
            ['booking_id' => $bookingId],
            array_merge($data, ['recorded_at' => now()]),
        );
    }

    public function patientHistory(string $patientName, ?string $phone = null): Collection
    {
        $bookingQuery = Booking::where('patient_name', $patientName);
        if ($phone) {
            $bookingQuery->orWhere('patient_phone', $phone);
        }
        $bookingIds = $bookingQuery->pluck('id');

        return ClinicSheet::with(['booking:id,file_no,visit_date,dept', 'doctor:id,name'])
            ->whereIn('booking_id', $bookingIds)
            ->latest('recorded_at')
            ->get();
    }
}
