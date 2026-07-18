<?php

namespace Modules\Accounting\Enums;

enum AccountNature: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'مدين',
            self::Credit => 'دائن',
        };
    }
}
