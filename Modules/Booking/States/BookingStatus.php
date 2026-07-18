<?php

namespace Modules\Booking\States;

use Modules\Booking\Models\Booking;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Booking>
 */
abstract class BookingStatus extends State
{
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(WaitingState::class)
            ->allowTransition(WaitingState::class, ConfirmedState::class)
            ->allowTransition(ConfirmedState::class, InProgressState::class)
            ->allowTransition(InProgressState::class, CompletedState::class)
            ->allowTransition(
                [WaitingState::class, ConfirmedState::class, InProgressState::class],
                CancelledState::class
            );
    }
}
