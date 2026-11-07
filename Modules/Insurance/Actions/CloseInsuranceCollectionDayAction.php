<?php

namespace Modules\Insurance\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Insurance\Models\InsuranceCollectionClosure;
use Modules\Insurance\Services\InsuranceAuditService;

class CloseInsuranceCollectionDayAction
{
    public function __construct(private readonly InsuranceAuditService $audit) {}

    public function execute(User $user): InsuranceCollectionClosure
    {
        return DB::transaction(function () use ($user): InsuranceCollectionClosure {
            if (InsuranceCollectionClosure::whereDate('collection_date', today())->exists()) {
                throw ValidationException::withMessages(['collection_date' => 'اليوم مغلق بالفعل.']);
            }
            $closure = InsuranceCollectionClosure::create(['collection_date' => today(), 'closed_by' => $user->id, 'closed_at' => now()]);
            $this->audit->record('day_close', 'تم إغلاق يوم التحصيل '.today()->toDateString(), $user);

            return $closure;
        });
    }
}
