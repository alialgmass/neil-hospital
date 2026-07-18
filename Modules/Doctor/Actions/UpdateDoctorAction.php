<?php

namespace Modules\Doctor\Actions;

use Modules\Admin\Services\ActivityLogService;
use Modules\Doctor\Models\Doctor;

class UpdateDoctorAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(Doctor $doctor, array $data): Doctor
    {
        $doctor->update($data);

        $this->activityLogService->log(
            action: 'update',
            module: 'doctors',
            recordId: $doctor->id,
            description: "تعديل بيانات الطبيب: {$doctor->name}",
            newValues: $data,
        );

        return $doctor->fresh();
    }
}
