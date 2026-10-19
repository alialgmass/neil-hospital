<?php

namespace Modules\Insurance\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\ApprovedState;
use Modules\Insurance\States\PaidState;

class RecordClaimCollectionAction
{
    /** A deduction above this share of the amount needs a written reason. */
    public const REASON_THRESHOLD = 0.10;

    public function __construct(
        private readonly UpdateInsuranceClaimAction $updateClaim,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Record the remittance of an approved claim. The amount is the approved
     * basis; paid = amount − deduction. The journal posting is the existing
     * collection posting driven by UpdateInsuranceClaimAction.
     *
     * @throws ValidationException
     */
    public function execute(InsuranceClaim $claim, float $amount, float $discount, ?string $reason, int $userId): InsuranceClaim
    {
        $reason = filled($reason) ? trim($reason) : null;

        if ($amount > 0 && $discount / $amount > self::REASON_THRESHOLD && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => 'الخصم أعلى من ١٠٪ — لازم تكتب سبب الخصم قبل التأكيد.',
            ]);
        }

        return DB::transaction(function () use ($claim, $amount, $discount, $reason, $userId): InsuranceClaim {
            $claim = InsuranceClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if (! $claim->status instanceof ApprovedState) {
                throw ValidationException::withMessages([
                    'stage' => 'التحصيل متاح فقط للمطالبات المعتمدة.',
                ]);
            }

            $claim = $this->updateClaim->execute($claim, [
                'status' => PaidState::$name,
                'approved_amount' => $amount,
                'paid_amount' => $amount - $discount,
                'deduction' => $discount,
                'deduction_reason' => $reason,
                'collected_by' => $userId,
                'collected_at' => now(),
                'payment_date' => now()->toDateString(),
            ]);

            $this->activityLog->log(
                action: 'collect',
                module: 'insurance_collection',
                recordId: $claim->id,
                description: sprintf(
                    'تحصيل %s ج.م — خصم %s%s',
                    number_format($amount - $discount),
                    number_format($discount),
                    $reason ? " — سبب: {$reason}" : '',
                ),
            );

            return $claim;
        });
    }
}
