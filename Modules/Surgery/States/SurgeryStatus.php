<?php

namespace Modules\Surgery\States;

use Modules\Surgery\Models\Surgery;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Surgery>
 */
abstract class SurgeryStatus extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(ScheduledState::class)
            ->allowTransition(ScheduledState::class, PrepState::class)
            ->allowTransition(PrepState::class, InProgressState::class)
            ->allowTransition(InProgressState::class, CompletedState::class)
            ->allowTransition(
                [ScheduledState::class, PrepState::class, InProgressState::class],
                CancelledState::class
            );
    }
}
