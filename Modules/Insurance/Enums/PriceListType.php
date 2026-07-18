<?php

namespace Modules\Insurance\Enums;

enum PriceListType: string
{
    case Cash = 'cash';
    case Insurance = 'insurance';
    case Vip = 'vip';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Insurance => 'تأمين',
            self::Vip => 'VIP',
            self::Special => 'خاص',
        };
    }
}
