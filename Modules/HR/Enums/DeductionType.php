<?php

namespace Modules\HR\Enums;

enum DeductionType: string
{
    case Days = 'days';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Days => 'أيام',
            self::Amount => 'مبلغ',
        };
    }
}
