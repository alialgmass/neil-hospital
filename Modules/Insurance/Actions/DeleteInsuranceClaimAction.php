<?php

namespace Modules\Insurance\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;

class DeleteInsuranceClaimAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(InsuranceClaim $claim): void
    {
        if (! ($claim->status instanceof DraftState)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن حذف مطالبة بعد إرسالها.',
            ]);
        }

        $this->activityLogService->log(
            action: 'delete',
            module: 'insurance_claims',
            recordId: $claim->id,
            description: "حذف مطالبة تأمين للمريض: {$claim->patient_name}",
        );

        $claim->delete();
    }
}
