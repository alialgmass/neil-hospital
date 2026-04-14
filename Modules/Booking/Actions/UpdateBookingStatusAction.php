<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class UpdateBookingStatusAction
{
    /** Valid transitions: from → allowed next statuses */
    private const TRANSITIONS = [
        'waiting'     => ['confirmed', 'cancelled'],
        'confirmed'   => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed'   => [],
        'cancelled'   => [],
    ];

    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(string $id, string $newStatus, ?string $cancelReason = null): Booking
    {
        $booking = $this->bookingRepository->findOrFail($id);
        $currentStatus = $booking->status;

        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن الانتقال من حالة \"{$currentStatus}\" إلى \"{$newStatus}\".",
            ]);
        }

        if ($newStatus === 'cancelled' && empty($cancelReason)) {
            throw ValidationException::withMessages([
                'cancel_reason' => 'يجب تحديد سبب الإلغاء.',
            ]);
        }

        $updated = $this->bookingRepository->updateStatus($id, $newStatus, $cancelReason);

        $this->activityLog->log(
            action:      'status_changed',
            module:      'booking',
            recordId:    $booking->id,
            description: "تغيير حالة الحجز {$booking->file_no}: {$currentStatus} → {$newStatus}",
        );

        return $updated;
    }
}
