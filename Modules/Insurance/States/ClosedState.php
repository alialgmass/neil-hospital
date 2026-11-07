<?php

namespace Modules\Insurance\States;

class ClosedState extends ClaimStatus
{
    public static string $name = 'closed';

    public function label(): string
    {
        return 'مقفولة';
    }
}
