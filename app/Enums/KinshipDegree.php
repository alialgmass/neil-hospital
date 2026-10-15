<?php

namespace App\Enums;

enum KinshipDegree: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Son = 'son';
    case Companion = 'companion';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'الوالد',
            self::Mother => 'الوالدة',
            self::Son => 'الابن',
            self::Companion => 'مرافق',
        };
    }
}
