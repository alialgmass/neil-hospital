<?php

namespace Modules\HR\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Emergency = 'emergency';
    case Maternity = 'maternity';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'سنوية',
            self::Sick => 'مرضية',
            self::Unpaid => 'بدون راتب',
            self::Emergency => 'طارئة',
            self::Maternity => 'أمومة',
        };
    }
}
