<?php

namespace Modules\Booking\States;

class CompletedState extends BookingStatus
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'مكتمل';
    }
}
