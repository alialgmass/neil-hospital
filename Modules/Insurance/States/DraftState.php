<?php

namespace Modules\Insurance\States;

class DraftState extends ClaimStatus
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'مسودة';
    }
}
