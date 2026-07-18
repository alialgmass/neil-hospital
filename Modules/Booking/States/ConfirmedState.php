<?php

namespace Modules\Booking\States;

class ConfirmedState extends BookingStatus
{
    public static string $name = 'confirmed';

    public function label(): string
    {
        return 'مؤكد';
    }
}
