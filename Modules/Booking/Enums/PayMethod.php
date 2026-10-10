<?php

namespace Modules\Booking\Enums;

enum PayMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Insurance = 'insurance';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Card => 'بطاقة',
            self::Transfer => 'تحويل',
            self::Insurance => 'تأمين',
            self::Contract => 'تعاقد',
        };
    }

    /**
     * Deal types where the hospital is settled by a third party (insurer or
     * contracting entity) rather than by a cash payment against the booking.
     */
    public function isThirdParty(): bool
    {
        return match ($this) {
            self::Insurance, self::Contract => true,
            default => false,
        };
    }
}
