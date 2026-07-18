<?php

namespace Modules\Inventory\Enums;

enum CenterShareType: string
{
    case Percentage = 'pct';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية',
            self::Fixed => 'مبلغ ثابت',
        };
    }
}
