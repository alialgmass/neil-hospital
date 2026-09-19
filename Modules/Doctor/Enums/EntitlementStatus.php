<?php

namespace Modules\Doctor\Enums;

enum EntitlementStatus: string
{
    case Pending = 'pending';
    case Settled = 'settled';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'مستحق',
            self::Settled => 'مسدد',
            self::Void => 'ملغي',
        };
    }
}
