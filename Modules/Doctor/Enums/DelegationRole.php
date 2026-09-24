<?php

namespace Modules\Doctor\Enums;

enum DelegationRole: string
{
    case Delegate = 'delegate';
    case Anesthesia = 'anesthesia';

    public function label(): string
    {
        return match ($this) {
            self::Delegate => 'تفويض',
            self::Anesthesia => 'تخدير',
        };
    }
}
