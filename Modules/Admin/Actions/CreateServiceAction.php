<?php

namespace Modules\Admin\Actions;

use Modules\Admin\Services\ActivityLogService;
use Modules\Booking\Models\Service;

class CreateServiceAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(array $data): Service
    {
        $data['center_val'] = $data['center_val'] ?? 0;

        $service = Service::create($data);
        $this->computeShares($service);
        $service->refresh();

        $this->activityLogService->log(
            action: 'create',
            module: 'services',
            recordId: $service->id,
            description: "إضافة خدمة: {$service->name}",
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
