<?php

namespace Modules\Insurance\States;

class UnderReviewState extends ClaimStatus
{
    public static string $name = 'under_review';

    public function label(): string
    {
        return 'تحت المراجعة';
    }
}
