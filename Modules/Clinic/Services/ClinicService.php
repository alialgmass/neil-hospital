<?php

namespace Modules\Clinic\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;

class ClinicService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ClinicSheetRepositoryInterface $clinicSheetRepository,
    ) {}

    /** Today's clinic bookings for the queue view. */
    public function getTodaysQueue(?string $date = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $filterDate = $date ?? today()->toDateString();

        return Booking::with(['doctor:id,name', 'clinicSheet:id,booking_id,diagnosis'])
            ->where('dept', 'clinic')
            ->whereDate('visit_date', $filterDate)
            ->orderBy('visit_time')
            ->paginate(50);
    }

    /** Full history for a patient by name (or phone for disambiguation). */
    public function getPatientHistory(string $bookingId): array
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);

        $history = $this->clinicSheetRepository->patientHistory(
            $booking->patient_name,
            $booking->patient_phone,
        );

        return [
            'booking' => $booking,
            'history' => $history,
        ];
    }
}
