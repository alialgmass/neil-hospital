<?php

namespace Modules\Insurance\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Enums\InsuranceDecision;
use Modules\Insurance\Services\InsuranceAuditService;

class SetInsuranceDecisionAction
{
    public function __construct(private readonly InsuranceAuditService $audit) {}

    public function execute(Booking $booking, InsuranceDecision $decision, User $user): Booking
    {
        return DB::transaction(function () use ($booking, $decision, $user): Booking {
            $booking->update(['insurance_decision' => $decision]);
            $this->audit->record('reception_decision', "قرار التأمين: {$decision->value}", $user);

            return $booking->refresh();
        });
    }
}
