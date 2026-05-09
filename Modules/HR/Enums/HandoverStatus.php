<?php

namespace Modules\HR\Enums;

enum HandoverStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلق',
            self::Accepted => 'مقبول',
        };
    }
}
