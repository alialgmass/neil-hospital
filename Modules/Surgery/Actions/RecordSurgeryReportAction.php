<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Repositories\Contracts\SurgeryRepositoryInterface;
use Modules\Surgery\Services\SurgeryService;
use Modules\Surgery\States\CompletedState;

class RecordSurgeryReportAction
{
    public function __construct(
        private readonly SurgeryRepositoryInterface $surgeryRepository,
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(string $surgeryId, array $report): Surgery
    {
        $existing = $this->surgeryRepository->findOrFail($surgeryId);

        $surgery = $this->surgeryRepository->update($surgeryId, [
            'op_report' => $report['op_report'] ?? null,
            'post_op_notes' => $report['post_op_notes'] ?? null,
            'complications' => $report['complications'] ?? null,
            'status' => CompletedState::$name,
            'ended_at' => now(),
        ]);

        if ($existing->or_bed_id) {
            $this->surgeryService->markBedAvailable($existing->or_bed_id);
        }

        $this->activityLog->log(
            action: 'report_recorded',
            module: $surgery->dept->value,
            recordId: $surgeryId,
            description: "تسجيل تقرير العملية: {$surgeryId}",
        );

        return $surgery;
    }
}
