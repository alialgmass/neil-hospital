<?php

namespace Modules\Surgery\States;

class ScheduledState extends SurgeryStatus
{
    public static string $name = 'scheduled';

    public function label(): string
    {
        return 'مجدول';
    }
}
