<?php

namespace Modules\Booking\States;

class CancelledState extends BookingStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'ملغي';
    }
}
