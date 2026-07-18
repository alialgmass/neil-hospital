<?php

namespace Modules\Insurance\States;

class RejectedState extends ClaimStatus
{
    public static string $name = 'rejected';

    public function label(): string
    {
        return 'مرفوضة';
    }
}
