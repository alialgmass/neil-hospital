<?php

namespace Modules\Insurance\States;

class PaidState extends ClaimStatus
{
    public static string $name = 'paid';

    public function label(): string
    {
        return 'مسددة';
    }
}
