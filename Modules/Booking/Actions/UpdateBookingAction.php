<?php

namespace Modules\Booking\Actions;

use App\Enums\Department;
use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Booking\DTOs\BookingData;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Services\BookingService;
use Modules\Surgery\Actions\ScheduleSurgeryAction;
use Modules\Surgery\DTOs\SurgeryData;

class UpdateBookingAction
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ScheduleSurgeryAction $scheduleSurgery,
        private readonly ActivityLogService $activityLog,
        private readonly AutoPostBookingPaymentAction $autoPost,
    ) {}

    public function execute(string $id, BookingData $data): Booking
    {
        $old = $this->bookingService->findOrFail($id);

        // Paid bookings cannot be edited (financial integrity)
        if ($old->pay_status === PayStatus::Paid) {
            throw ValidationException::withMessages([
                'pay_status' => 'لا يمكن تعديل حجز مسدد بالكامل.',
            ]);
        }

        $booking = $this->bookingService->update($id, $data);

        if (in_array($data->dept, [Department::Surgery, Department::Lasik])) {
            $this->scheduleSurgery->execute(new SurgeryData(
                bookingId: $booking->id,
                dept: $data->dept,
                orBedId: $data->bedId,
                surgeonId: $data->doctorId,
                eye: $data->eyeSide,
            ));
        }

        // Auto-post accounting entries when payment is first confirmed
        if ($old->pay_status !== PayStatus::Paid && $booking->pay_status === PayStatus::Paid) {
            $this->autoPost->execute($booking);
        }

        $this->activityLog->log(
            action: 'updated',
            module: 'booking',
            recordId: $booking->id,
            description: "تعديل حجز: {$booking->patient_name} — {$booking->file_no}",
            oldValues: $old->toArray(),
            newValues: $booking->toArray(),
        );

        return $booking;
    }
}
