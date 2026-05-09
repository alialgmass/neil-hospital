<?php

namespace Modules\HR\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلقة',
            self::Approved => 'موافق عليها',
            self::Rejected => 'مرفوضة',
        };
    }
}
