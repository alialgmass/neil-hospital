<?php

namespace Modules\Surgery\States;

class CancelledState extends SurgeryStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'ملغية';
    }
}
