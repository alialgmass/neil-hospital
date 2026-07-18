<?php

namespace Modules\Insurance\Actions;

use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Services\InsuranceService;

class UpdateInsuranceCompanyAction
{
    public function __construct(
        private readonly InsuranceService $insuranceService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(string $id, array $data): InsuranceCompany
    {
        $company = $this->insuranceService->update($id, $data);

        $this->activityLogService->log(
            action: 'update',
            module: 'insurance',
            recordId: $company->id,
            description: "تعديل شركة تأمين: {$company->name}",
            newValues: $data,
        );

        return $company;
    }
}
