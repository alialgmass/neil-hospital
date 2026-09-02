<?php

namespace App\Enums;

enum Department: string
{
    case Clinic = 'clinic';
    case Labs = 'labs';
    case Surgery = 'surgery';
    case Lasik = 'lasik';
    case Laser = 'laser';
    case Pentacam = 'pentacam';

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'العيادة',
            self::Labs => 'الفحوصات',
            self::Surgery => 'العمليات',
            self::Lasik => 'الليزك',
            self::Laser => 'الليزر',
            self::Pentacam => 'البنتكام',
        };
    }
}
