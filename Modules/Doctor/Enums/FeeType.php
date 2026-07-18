<?php

namespace Modules\Doctor\Enums;

enum FeeType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية',
            self::Fixed => 'مبلغ ثابت',
            self::Insurance => 'تأمين',
        };
    }
}
