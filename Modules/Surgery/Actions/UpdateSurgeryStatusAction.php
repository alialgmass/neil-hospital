<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\States\CompletedState;
use Modules\Surgery\States\SurgeryStatus;

class UpdateSurgeryStatusAction
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function execute(string $id, string|SurgeryStatus $status): Surgery
    {
        $surgery = Surgery::findOrFail($id);
        if($surgery->status->canTransitionTo($status)){
            $surgery->status->transitionTo($status);
        }

        $statusLabel = $status instanceof SurgeryStatus ? $status->label() : $status;

        if($surgery->status->equals(CompletedState::class)){
            $this->disactiveBid($surgery);
        }

        $this->activityLog->log(
            action: 'status_updated',
            module: $surgery->dept->value ?? 'surgery',
            recordId: $id,
            description: "تغيير حالة الإجراء إلى: {$statusLabel}",
        );

        return $surgery->fresh();
    }

    /**
     * @param $surgery
     * @return void
     */
    public function disactiveBid($surgery): void
    {
        $surgery->update([
            'or_bed_id' => null
        ]);
    }
}
