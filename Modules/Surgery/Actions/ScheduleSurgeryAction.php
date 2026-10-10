<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Actions\SyncBookingDoctorDelegationsAction;
use Modules\Doctor\Actions\SyncDoctorEntitlementAction;
use Modules\Surgery\DTOs\SurgeryData;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;

class ScheduleSurgeryAction
{
    public function __construct(
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
        private readonly SyncBookingDoctorDelegationsAction $syncDelegations,
        private readonly SyncDoctorEntitlementAction $syncDoctorEntitlement,
    ) {}

    public function execute(SurgeryData $data): Surgery
    {
        if ($data->orBedId && $data->scheduledAt) {
            if (! $this->surgeryService->isBedAvailable($data->orBedId, $data->scheduledAt)) {
                throw ValidationException::withMessages([
                    'or_bed_id' => 'السرير المحدد محجوز في هذا الوقت.',
                ]);
            }
        }

        $existing = $this->surgeryService->findByBooking($data->bookingId);

        if ($existing) {
            $surgery = $this->surgeryService->update($existing->id, $data);
        } else {
            $surgery = $this->surgeryService->schedule($data);
        }

        $this->activityLog->log(
            action: 'scheduled',
            module: $data->dept->value,
            recordId: $surgery->id,
            description: "جدولة {$data->dept->label()} للحجز: {$data->bookingId}",
        );

        $booking = Booking::findOrFail($data->bookingId);
        $this->syncDelegations->execute($booking, $data->delegations);

        // Re-syncs the primary doctor's entitlement (now netted of the
        // delegated total) and, for insurance/contract bookings, immediately
        // accrues the delegated/anesthesia doctors — a no-op for cash
        // bookings, which settle delegated dues at payment time instead
        // (see PostDelegatedDoctorDuesForPaymentAction).
        $this->syncDoctorEntitlement->execute($booking);

        return $surgery;
    }
}
