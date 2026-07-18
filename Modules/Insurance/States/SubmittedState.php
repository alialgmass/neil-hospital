<?php

namespace Modules\Insurance\States;

class SubmittedState extends ClaimStatus
{
    public static string $name = 'submitted';

    public function label(): string
    {
        return 'مُرسلة';
    }
}
