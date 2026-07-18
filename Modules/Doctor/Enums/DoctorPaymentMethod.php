<?php

namespace Modules\Doctor\Enums;

enum DoctorPaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Transfer => 'تحويل',
        };
    }
}
