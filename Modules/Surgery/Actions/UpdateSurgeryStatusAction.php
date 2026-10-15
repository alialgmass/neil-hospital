<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Modules\Booking\States\CompletedElectronicState;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;
use Modules\Surgery\States\CompletedState;
use Modules\Surgery\States\SurgeryStatus;

class UpdateSurgeryStatusAction
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SurgeryService $surgeryService,
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly AutoPostBookingPaymentAction $autoPost,
    ) {}

    public function execute(string $id, string|SurgeryStatus $status): Surgery
    {
        $surgery = Surgery::findOrFail($id);
        if ($surgery->status->canTransitionTo($status)) {
            $surgery->status->transitionTo($status);
        }

        $statusLabel = $status instanceof SurgeryStatus ? $status->label() : $status;

        // Free the bed for new scheduling, but keep the surgery's bed link intact
        // so the completed case still shows on the beds screen.
        if ($surgery->status->equals(CompletedState::class) && $surgery->or_bed_id) {
            $this->surgeryService->markBedAvailable($surgery->or_bed_id);
        }

        // Sync the linked booking: completing a surgery from the operations side
        // marks its booking "مكتمل - إلكتروني" so it drops off the booking screen
        // like a normally-completed booking, and posts accounting the same way.
        if ($surgery->status->equals(CompletedState::class) && $surgery->booking_id) {
            $booking = $this->bookingRepository->findOrFail($surgery->booking_id);

            if ($booking->status->canTransitionTo(CompletedElectronicState::class)) {
                $booking->status->transitionTo(CompletedElectronicState::class);

                if ($booking->pay_status === PayStatus::Paid) {
                    $this->autoPost->execute($booking);
                }
            }
        }

        $this->activityLog->log(
            action: 'status_updated',
            module: $surgery->dept->value ?? 'surgery',
            recordId: $id,
            description: "تغيير حالة الإجراء إلى: {$statusLabel}",
        );

        return $surgery->fresh();
    }
}
