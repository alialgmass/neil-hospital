<?php

namespace Modules\Doctor\Enums;

use Modules\Booking\Enums\PayMethod;

enum EntitlementSource: string
{
    case Insurance = 'insurance';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::Insurance => 'تأمين',
            self::Contract => 'تعاقد',
        };
    }

    public static function fromPayMethod(?PayMethod $payMethod): ?self
    {
        return match ($payMethod) {
            PayMethod::Insurance => self::Insurance,
            PayMethod::Contract => self::Contract,
            default => null,
        };
    }
}
