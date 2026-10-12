<?php

namespace Modules\Insurance\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\ApprovedState;
use Modules\Insurance\States\ClosedState;
use Modules\Insurance\States\DraftState;
use Modules\Insurance\States\PaidState;
use Modules\Insurance\States\RejectedState;
use Modules\Insurance\States\SubmittedState;
use Modules\Insurance\States\UnderReviewState;

class AdvanceClaimStageAction
{
    public function __construct(
        private readonly UpdateInsuranceClaimAction $updateClaim,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Move a claim one legal step along the collection cycle. Collecting
     * (approved → paid) is deliberately not an "advance": it needs the amount
     * received, so it goes through RecordClaimCollectionAction.
     *
     * @throws ValidationException
     */
    public function execute(InsuranceClaim $claim): InsuranceClaim
    {
        $from = $claim->status;
        $data = match (true) {
            $from instanceof DraftState, $from instanceof RejectedState => $this->submissionData($claim),
            $from instanceof SubmittedState => ['status' => UnderReviewState::$name],
            $from instanceof UnderReviewState => ['status' => ApprovedState::$name],
            $from instanceof PaidState => ['status' => ClosedState::$name],
            default => throw ValidationException::withMessages([
                'stage' => $from instanceof ApprovedState
                    ? 'المطالبة المعتمدة تُنقل للمسددة بتسجيل التحصيل.'
                    : 'لا توجد مرحلة تالية لهذه المطالبة.',
            ]),
        };

        $claim = $this->updateClaim->execute($claim, $data);

        $this->activityLog->log(
            action: 'advance_stage',
            module: 'insurance_collection',
            recordId: $claim->id,
            description: "نقل مرحلة: من \"{$from->label()}\" إلى \"{$claim->status->label()}\"",
        );

        return $claim;
    }

    /**
     * @return array<string, string>
     */
    private function submissionData(InsuranceClaim $claim): array
    {
        return [
            'status' => SubmittedState::$name,
            'submission_date' => now()->toDateString(),
            'claim_reference' => $claim->claim_reference ?: 'REF-'.strtoupper(substr($claim->id, -8)),
        ];
    }
}
