<?php

namespace App\Enums;

enum EyeSide: string
{
    case OD = 'OD';
    case OS = 'OS';
    case OU = 'OU';

    public function label(): string
    {
        return match ($this) {
            self::OD => 'العين اليمنى',
            self::OS => 'العين اليسرى',
            self::OU => 'كلتا العينين',
        };
    }
}
