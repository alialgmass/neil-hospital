<?php

namespace Modules\Booking\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\BookingStatus;
use Modules\Booking\States\CancelledState;
use Modules\Booking\States\CompletedState;
use Modules\Surgery\Services\SurgeryService;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

class UpdateBookingStatusAction
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly SurgeryService $surgeryService,
        private readonly AutoPostBookingPaymentAction $autoPost,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(string $id, string|BookingStatus $newStatus, ?string $cancelReason = null): Booking
    {
        $booking = $this->bookingRepository->findOrFail($id);
        $oldStatus = (string) $booking->status;

        try {
            $booking->status->transitionTo($newStatus);
        } catch (CouldNotPerformTransition $e) {
            $statusStr = $newStatus instanceof BookingStatus ? (string) $newStatus : $newStatus;
            throw ValidationException::withMessages([
                'status' => "لا يمكن الانتقال من حالة \"{$oldStatus}\" إلى \"{$statusStr}\".",
            ]);
        }

        if ($booking->status instanceof CancelledState) {
            $booking->update(['cancel_reason' => $cancelReason]);
        }

        // 1. Bed Management & Surgery Sync
        if ($booking->status instanceof CompletedState || $booking->status instanceof CancelledState) {
            $this->surgeryService->updateStatusByBooking($booking->id, (string) $booking->status);
        }

        // 2. Accounting Sync
        if ($booking->status instanceof CompletedState && $booking->pay_status === PayStatus::Paid) {
            $this->autoPost->execute($booking);
        }

        $this->activityLog->log(
            action: 'status_changed',
            module: 'booking',
            recordId: $booking->id,
            description: "تغيير حالة الحجز {$booking->file_no}: {$oldStatus} → ".(string) $booking->status,
        );

        return $booking->fresh();
    }
}
