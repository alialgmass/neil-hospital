<?php

namespace Modules\Insurance\Enums;

enum DepositDecision: string
{
    case Waiting = 'waiting';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'في انتظار القرار',
            self::Approved => 'صدر القرار: موافقة',
            self::Rejected => 'صدر القرار: رفض',
        };
    }
}
