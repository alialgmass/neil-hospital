<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;

class RecordSuppliesUsedAction
{
    public function __construct(
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(SuppliesUsedData $data): Surgery
    {
        $surgery = $this->surgeryService->recordSupplies($data);

        $this->activityLog->log(
            action:      'supplies_recorded',
            module:      get_class($surgery->dept),
            recordId:    $data->surgeryId,
            description: "تسجيل مستلزمات: إجمالي = {$surgery->supply_total} ج.م",
        );

        return $surgery;
    }
}
