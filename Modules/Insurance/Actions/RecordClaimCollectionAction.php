<?php

namespace Modules\Insurance\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCollectionApproval;
use Modules\Insurance\Services\InsuranceAuditService;
use Modules\Insurance\States\ApprovedState;
use Modules\Insurance\States\PaidState;

class RecordClaimCollectionAction
{
    public const REASON_THRESHOLD = 0.10;

    public function __construct(
        private readonly UpdateInsuranceClaimAction $updateClaim,
        private readonly ActivityLogService $activityLog,
        private readonly InsuranceAuditService $audit,
    ) {}

    public function execute(InsuranceClaim $claim, float $amount, float $discount, ?string $reason, int $userId): InsuranceClaim
    {
        $reason = filled($reason) ? trim($reason) : null;

        return DB::transaction(function () use ($claim, $amount, $discount, $reason, $userId): InsuranceClaim {
            $claim = InsuranceClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            $this->validate($claim, $amount, $discount, $reason);
            $this->assertOpenDate();
            $net = round($amount - $discount, 2);
            $needsApproval = $discount / $amount > self::REASON_THRESHOLD || $net >= (float) config('insurance.collection_approval_threshold', 20000);
            if ($needsApproval) {
                if (InsuranceCollectionApproval::where('claim_id', $claim->id)->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['approval' => 'هذه المطالبة لديها تحصيل بانتظار الاعتماد.']);
                }
                $approval = InsuranceCollectionApproval::create([
                    'claim_id' => $claim->id, 'recorded_by' => $userId, 'recorded_at' => now(),
                    'amount' => $amount, 'discount' => $discount, 'tax' => $discount, 'net' => $net,
                    'reason' => $reason, 'why' => $discount / $amount > self::REASON_THRESHOLD ? 'discount' : 'threshold',
                ]);
                $this->audit->record('collection_pending', "تحصيل بانتظار الاعتماد: {$approval->id}", auth()->user(), $claim);

                return $claim;
            }

            return $this->post($claim, $amount, $discount, $reason, $userId);
        });
    }

    public function post(InsuranceClaim $claim, float $amount, float $discount, ?string $reason, int $userId): InsuranceClaim
    {
        $claim = $this->updateClaim->execute($claim, [
            'status' => PaidState::$name, 'approved_amount' => $amount, 'paid_amount' => $amount - $discount,
            'deduction' => $discount, 'deduction_reason' => $reason, 'collected_by' => $userId,
            'collected_at' => now(), 'payment_date' => now()->toDateString(),
        ]);
        $this->activityLog->log(action: 'collect', module: 'insurance_collection', recordId: $claim->id, description: 'تم تسجيل تحصيل صافي '.number_format($amount - $discount));
        $this->audit->record('collection_recorded', 'تم ترحيل التحصيل النهائي', User::find($userId), $claim);

        return $claim;
    }

    private function validate(InsuranceClaim $claim, float $amount, float $discount, ?string $reason): void
    {
        if (! $claim->status instanceof ApprovedState) {
            throw ValidationException::withMessages(['stage' => 'التحصيل متاح فقط للمطالبات المعتمدة.']);
        }
        $due = max(0, (float) ($claim->approved_amount ?? $claim->insurance_share) - (float) $claim->rejected_amount);
        if ($amount > $due) {
            throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المستحق بعد الرفض.']);
        }
        if ($discount > $amount) {
            throw ValidationException::withMessages(['discount' => 'الخصم لا يمكن أن يتجاوز المبلغ.']);
        }
        if ($discount / $amount > self::REASON_THRESHOLD && $reason === null) {
            throw ValidationException::withMessages(['discount_reason' => 'الخصم الأكبر من 10٪ يحتاج سبباً مكتوباً.', 'reason' => 'الخصم الأكبر من 10٪ يحتاج سبباً مكتوباً.']);
        }
    }

    private function assertOpenDate(): void
    {
        if (DB::table('insurance_collection_closures')->whereDate('collection_date', now()->toDateString())->exists()) {
            throw ValidationException::withMessages(['collection_date' => 'تم إغلاق يوم التحصيل الحالي.']);
        }
    }
}
