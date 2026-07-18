<?php

namespace Modules\Surgery\States;

class CompletedState extends SurgeryStatus
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'مكتملة';
    }
}
