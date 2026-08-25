<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;
use Modules\Surgery\States\CompletedState;
use Modules\Surgery\States\SurgeryStatus;

class UpdateSurgeryStatusAction
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SurgeryService $surgeryService,
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

        $this->activityLog->log(
            action: 'status_updated',
            module: $surgery->dept->value ?? 'surgery',
            recordId: $id,
            description: "تغيير حالة الإجراء إلى: {$statusLabel}",
        );

        return $surgery->fresh();
    }
}
