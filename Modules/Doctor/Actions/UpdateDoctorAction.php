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
        $services = $data['services'] ?? null;
        $delegationServices = $data['delegation_services'] ?? null;
        unset($data['services'], $data['delegation_services']);

        $doctor->update($data);

        if ($services !== null) {
            $doctor->syncServiceFees($services);
        }

        if ($delegationServices !== null) {
            $doctor->syncDelegationFees($delegationServices);
        }

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
