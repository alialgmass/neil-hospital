<?php

namespace Modules\Doctor\Enums;

enum DebtEntryType: string
{
    case Incurred = 'incurred';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Incurred => 'دين مستحدث',
            self::Settled => 'تسوية دين',
        };
    }
}
