<?php

namespace Modules\Surgery\States;

class InProgressState extends SurgeryStatus
{
    public static string $name = 'in_progress';

    public function label(): string
    {
        return 'قيد التنفيذ';
    }
}
