<?php

namespace Modules\Accounting\Enums;

/**
 * The three control accounts that الدليل المحاسبي v2.0 splits into one
 * detail (sub-ledger) account per party: doctors (2201–2299 under 2010),
 * suppliers (2301–2399 under 2020) and employees (2401–2499 under 2030).
 * The control account itself is never posted to — its balance is the sum
 * of its children.
 */
enum Subledger: string
{
    case Doctor = 'doctor';
    case Supplier = 'supplier';
    case Employee = 'employee';

    public function masterCode(): AccountCode
    {
        return match ($this) {
            self::Doctor => AccountCode::DOCTOR_PAYABLE,
            self::Supplier => AccountCode::SUPPLIER_PAYABLE,
            self::Employee => AccountCode::NET_SALARY_PAYABLE,
        };
    }

    /** Table holding the party and its `payable_account_id` link. */
    public function table(): string
    {
        return match ($this) {
            self::Doctor => 'doctors',
            self::Supplier => 'suppliers',
            self::Employee => 'employees',
        };
    }

    /** First code of the party range (the guide's first cohort starts here). */
    public function firstCode(): int
    {
        return match ($this) {
            self::Doctor => 2201,
            self::Supplier => 2301,
            self::Employee => 2401,
        };
    }

    /**
     * Last code a real party may take. The very last code of the hundred
     * (2299/2399/2499) is reserved for the "unidentified — for review"
     * bucket that historical lines with no traceable owner land on.
     */
    public function lastCode(): int
    {
        return $this->unassignedCode() - 1;
    }

    public function unassignedCode(): int
    {
        return match ($this) {
            self::Doctor => 2299,
            self::Supplier => 2399,
            self::Employee => 2499,
        };
    }

    public function namePrefix(): string
    {
        return match ($this) {
            self::Doctor, self::Supplier => 'دائنون — ',
            self::Employee => 'مستحق راتب — ',
        };
    }

    public function unassignedName(): string
    {
        return match ($this) {
            self::Doctor => 'دائنون — أطباء غير محدّد (للمراجعة)',
            self::Supplier => 'دائنون — موردون غير محدّد (للمراجعة)',
            self::Employee => 'مستحق راتب — غير محدّد (للمراجعة)',
        };
    }

    public static function forMasterCode(string $code): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->masterCode()->value === $code) {
                return $case;
            }
        }

        return null;
    }
}
