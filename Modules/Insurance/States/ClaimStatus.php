<?php

namespace Modules\Insurance\States;

use Modules\Insurance\Models\InsuranceClaim;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<InsuranceClaim>
 */
abstract class ClaimStatus extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, SubmittedState::class)
            ->allowTransition(SubmittedState::class, ApprovedState::class)
            ->allowTransition(SubmittedState::class, RejectedState::class)
            ->allowTransition(ApprovedState::class, PaidState::class);
    }
}
