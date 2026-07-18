<?php

namespace Modules\Labs\Actions;

use Modules\Admin\Services\ActivityLogService;
use Modules\Labs\Models\DiagnosticResult;

class StoreLabResultAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(string $bookingId, array $data, int $technicianId): DiagnosticResult
    {
        $result = DiagnosticResult::create([
            ...$data,
            'booking_id' => $bookingId,
            'technician_id' => $technicianId,
            'recorded_at' => now(),
        ]);

        $this->activityLogService->log(
            action: 'result_recorded',
            module: 'labs',
            recordId: $result->id,
            description: "تسجيل نتيجة: {$result->test_name} للحجز {$bookingId}",
        );

        return $result;
    }
}
