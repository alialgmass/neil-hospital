<?php

namespace Modules\Booking\Enums;

enum PayMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Card => 'بطاقة',
            self::Transfer => 'تحويل',
            self::Insurance => 'تأمين',
        };
    }
}
