<?php

namespace Modules\Accounting\Enums;

enum CostCenter: string
{
    case Clinic = 'CC-CLINIC';
    case Lab = 'CC-LAB';
    case Surgery = 'CC-SURG';
    case Lasik = 'CC-LASIK';
    case Laser = 'CC-LASER';
    case Insurance = 'CC-INS';
    case Doctors = 'CC-DR';
    case Inventory = 'CC-INV';
    case Equipment = 'CC-EQUIP';

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'العيادة الخارجية',
            self::Lab => 'الفحوصات والمختبر',
            self::Surgery => 'غرفة العمليات',
            self::Lasik => 'وحدة الليزك',
            self::Laser => 'الليزر التشخيصي',
            self::Insurance => 'التأمين الصحي',
            self::Doctors => 'الأطباء والمستحقات',
            self::Inventory => 'إدارة المخزون',
            self::Equipment => 'الأجهزة والمعدات',
        };
    }
}
