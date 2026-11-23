<?php

namespace Modules\Insurance\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Insurance\Models\InsuranceCollectionApproval;
use Modules\Insurance\Services\InsuranceAuditService;

class ApproveInsuranceCollectionAction
{
    public function __construct(private readonly RecordClaimCollectionAction $collections, private readonly InsuranceAuditService $audit) {}

    public function approve(InsuranceCollectionApproval $approval, User $user): void
    {
        DB::transaction(function () use ($approval, $user): void {
            $approval = InsuranceCollectionApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'تم اتخاذ قرار لهذا التحصيل من قبل.']);
            }
            if (DB::table('insurance_collection_closures')->whereDate('collection_date', today())->exists()) {
                throw ValidationException::withMessages(['collection_date' => 'تم إغلاق يوم التحصيل الحالي.']);
            }
            if ($approval->recorded_by === $user->id && ! $user->hasRole('admin')) {
                throw ValidationException::withMessages(['approval' => 'لا يجوز اعتماد تحصيلك بنفسك.']);
            }
            $this->collections->post($approval->claim, $approval->amount, $approval->discount, $approval->reason, $user->id);
            $approval->update(['status' => 'approved', 'decided_by' => $user->id, 'decided_at' => now()]);
            $this->audit->record('collection_approved', 'تم اعتماد التحصيل وترحيله', $user, $approval->claim);
        });
    }

    public function reject(InsuranceCollectionApproval $approval, User $user, string $reason): void
    {
        DB::transaction(function () use ($approval, $user, $reason): void {
            $approval = InsuranceCollectionApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['approval' => 'تم اتخاذ قرار لهذا التحصيل من قبل.']);
            }
            $approval->update(['status' => 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_reason' => $reason]);
            $this->audit->record('collection_rejected', 'تم رفض التحصيل: '.$reason, $user, $approval->claim);
        });
    }
}
