<?php

namespace Modules\Insurance\States;

class ApprovedState extends ClaimStatus
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'معتمدة';
    }
}
