<?php

namespace Modules\Inventory\Enums;

enum PermitType: string
{
    case Out = 'out';
    case In = 'in';

    public function label(): string
    {
        return match ($this) {
            self::Out => 'صرف',
            self::In => 'إضافة',
        };
    }
}
