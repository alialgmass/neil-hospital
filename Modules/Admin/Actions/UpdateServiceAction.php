<?php

namespace Modules\Admin\Actions;

use Modules\Admin\Services\ActivityLogService;
use Modules\Booking\Models\Service;

class UpdateServiceAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(Service $service, array $data): Service
    {
        $service->update($data);
        $this->computeShares($service->fresh());
        $service->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'services',
            recordId: $service->id,
            description: "تعديل خدمة: {$service->name}",
            newValues: $data,
        );

        return $service;
    }

    /**
     * Splits the configured price into the hospital's center cut only — the
     * doctor's fee is never derived from the service price/center split.
     * It comes exclusively from the Doctors module (per-doctor per-service
     * fee, falling back to the service's default_dr_fee — see
     * SyncDoctorEntitlementAction::resolveFee()).
     */
    private function computeShares(Service $service): void
    {
        $price = (float) $service->price;
        $centerShare = $service->center_type === 'pct'
            ? round($price * ($service->center_val / 100), 2)
            : (float) $service->center_val;

        $service->update(['center_share' => $centerShare]);
    }
}
