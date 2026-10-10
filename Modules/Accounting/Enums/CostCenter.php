<?php

namespace Modules\Accounting\Enums;

use App\Enums\Department;

enum CostCenter: string
{
    case Clinic = 'CC-CLINIC';
    case Lab = 'CC-LAB';
    case Surgery = 'CC-SURG';
    case Lasik = 'CC-LASIK';
    case Laser = 'CC-LASER';
    case Pentacam = 'CC-PENTACAM';
    case Insurance = 'CC-INS';
    case Doctors = 'CC-DR';
    case Inventory = 'CC-INV';
    case Equipment = 'CC-EQUIP';
    case Admin = 'CC-ADMIN';

    /**
     * The cost center a department's revenue and direct costs are tagged
     * with (guide §2.5). A null department (e.g. a general store issue)
     * falls back to the given default.
     */
    public static function forDepartment(?Department $dept, self $default = self::Inventory): self
    {
        return match ($dept) {
            Department::Clinic => self::Clinic,
            Department::Labs => self::Lab,
            Department::Surgery => self::Surgery,
            Department::Lasik => self::Lasik,
            Department::Laser => self::Laser,
            Department::Pentacam => self::Pentacam,
            null => $default,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'العيادة الخارجية',
            self::Lab => 'الفحوصات والمختبر',
            self::Surgery => 'غرفة العمليات',
            self::Lasik => 'وحدة الليزك',
            self::Laser => 'الليزر التشخيصي',
            self::Pentacam => 'وحدة البنتكام',
            self::Insurance => 'التأمين الصحي',
            self::Doctors => 'الأطباء والمستحقات',
            self::Inventory => 'إدارة المخزون',
            self::Equipment => 'الأجهزة والمعدات',
            self::Admin => 'الإدارة والموارد البشرية',
        };
    }
}
