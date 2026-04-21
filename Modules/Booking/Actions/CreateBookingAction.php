<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Services\SurgeryService;

class CreateBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(BookingData $data, int $createdBy): Booking
    {
        $booking = $this->bookingService->create($data, $createdBy);

        if (in_array($data->dept, ['surgery', 'lasik'])) {
        $aa=    $this->surgeryService->schedule(new SurgeryData(
                bookingId: $booking->id,
                dept: $data->dept,
                orBedId: $data->bedId,
                surgeonId: $data->doctorId,
            ));
            dd($aa);
        }

        $this->activityLog->log(
            action: 'created',
            module: 'booking',
            recordId: $booking->id,
            description: "حجز جديد: {$booking->patient_name} — {$booking->file_no}",
            newValues: $booking->toArray(),
        );

        return $booking;
    }
}
