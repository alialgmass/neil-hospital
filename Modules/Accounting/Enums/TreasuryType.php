<?php

namespace Modules\Accounting\Enums;

enum TreasuryType: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'وارد',
            self::Out => 'صادر',
        };
    }
}
