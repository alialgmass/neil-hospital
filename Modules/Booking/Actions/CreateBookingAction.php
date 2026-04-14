<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;

class CreateBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(BookingData $data, int $createdBy): Booking
    {
        $booking = $this->bookingService->create($data, $createdBy);

        $this->activityLog->log(
            action:      'created',
            module:      'booking',
            recordId:    $booking->id,
            description: "حجز جديد: {$booking->patient_name} — {$booking->file_no}",
            newValues:   $booking->toArray(),
        );

        return $booking;
    }
}
