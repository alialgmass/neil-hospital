<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\CancelledState;
use Modules\Booking\States\CompletedState;
use Modules\Surgery\Services\SurgeryService;

class CancelBookingAction
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Cancel a booking.
     *
     * @param  bool  $adminOverride  Admins can cancel even paid bookings (with reason).
     */
    public function execute(string $id, string $cancelReason, bool $adminOverride = false): Booking
    {
        $booking = $this->bookingRepository->findOrFail($id);

        if ($booking->pay_status === PayStatus::Paid && ! $adminOverride) {
            throw ValidationException::withMessages([
                'pay_status' => 'لا يمكن إلغاء حجز مسدد بالكامل. تواصل مع المدير.',
            ]);
        }

        if ($booking->status instanceof CompletedState || $booking->status instanceof CancelledState) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن إلغاء حجز بحالة \"{$booking->status->label()}\".",
            ]);
        }

        $booking = $this->bookingRepository->updateStatus($id, 'cancelled', $cancelReason);

        $this->surgeryService->updateStatusByBooking($booking->id, 'cancelled');

        $this->activityLog->log(
            action: 'cancelled',
            module: 'booking',
            recordId: $booking->id,
            description: "إلغاء حجز: {$booking->file_no} — السبب: {$cancelReason}",
        );

        return $booking;
    }
}
