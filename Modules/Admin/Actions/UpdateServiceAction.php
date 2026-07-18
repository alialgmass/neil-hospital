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

    private function computeShares(Service $service): void
    {
        $price = (float) $service->price;
        $centerShare = $service->center_type === 'pct'
            ? round($price * ($service->center_val / 100), 2)
            : (float) $service->center_val;

        $service->update([
            'center_share' => $centerShare,
            'dr_share' => max(0, $price - $centerShare),
        ]);
    }
}
