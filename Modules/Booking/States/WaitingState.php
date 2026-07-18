<?php

namespace Modules\Booking\States;

class WaitingState extends BookingStatus
{
    public static string $name = 'waiting';

    public function label(): string
    {
        return 'في الانتظار';
    }
}
