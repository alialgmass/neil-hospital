<?php

namespace Modules\Insurance\Enums;

enum InsuranceDecision: string
{
    case None = 'none';
    case Deferred = 'deferred';
    case Issued = 'issued';
    case NotRequired = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::None => 'لم يُتخذ قرار',
            self::Deferred => 'مؤجل',
            self::Issued => 'صدر له مطالبة',
            self::NotRequired => 'لا يحتاج قرار',
        };
    }
}
