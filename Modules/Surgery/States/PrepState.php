<?php

namespace Modules\Surgery\States;

class PrepState extends SurgeryStatus
{
    public static string $name = 'prep';

    public function label(): string
    {
        return 'تحضير';
    }
}
