<?php

namespace Modules\Insurance\Actions;

use Modules\Accounting\Actions\AutoPostInsuranceClaimAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Booking\Enums\PayStatus;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\PaidState;
use Modules\Insurance\States\RejectedState;
use Modules\Insurance\States\SubmittedState;

class UpdateInsuranceClaimAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostInsuranceClaimAction $autoPost,
    ) {}

    public function execute(InsuranceClaim $claim, array $data): InsuranceClaim
    {
        $oldStatus = (string) $claim->status;

        $claim->update($data);
        $claim->refresh();

        $newStatus = (string) $claim->status;

        // Post journal when claim is submitted
        if ($newStatus === SubmittedState::$name && $oldStatus !== SubmittedState::$name) {
            $this->autoPost->onSubmit($claim);
        }

        // Post collection entry and mark booking paid when claim is collected
        if ($claim->status instanceof PaidState && $oldStatus !== PaidState::$name) {
            $this->autoPost->onCollect($claim);

            if ($claim->booking_id) {
                $claim->booking->update(['pay_status' => PayStatus::Paid->value]);
            }
        }

        // Reverse the submitted receivable/revenue entry when a claim is rejected
        if ($newStatus === RejectedState::$name && $oldStatus !== RejectedState::$name) {
            $this->autoPost->onReject($claim);
        }

        $this->activityLogService->log(
            action: 'update',
            module: 'insurance_claims',
            recordId: $claim->id,
            description: "تحديث مطالبة التأمين: {$oldStatus} → {$newStatus}",
            newValues: $data,
        );

        return $claim;
    }
}
