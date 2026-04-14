<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class CancelBookingAction
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
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

        if ($booking->pay_status === 'paid' && ! $adminOverride) {
            throw ValidationException::withMessages([
                'pay_status' => 'لا يمكن إلغاء حجز مسدد بالكامل. تواصل مع المدير.',
            ]);
        }

        if (in_array($booking->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن إلغاء حجز بحالة \"{$booking->status}\".",
            ]);
        }

        $booking = $this->bookingRepository->updateStatus($id, 'cancelled', $cancelReason);

        $this->activityLog->log(
            action:      'cancelled',
            module:      'booking',
            recordId:    $booking->id,
            description: "إلغاء حجز: {$booking->file_no} — السبب: {$cancelReason}",
        );

        return $booking;
    }
}
