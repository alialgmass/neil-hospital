<?php

namespace Modules\Booking\States;

class InProgressState extends BookingStatus
{
    public static string $name = 'in_progress';

    public function label(): string
    {
        return 'قيد التنفيذ';
    }
}
