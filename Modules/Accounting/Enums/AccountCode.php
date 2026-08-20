<?php

namespace Modules\Accounting\Enums;

use App\Enums\Department;
use Modules\Inventory\Enums\ItemCategory;

/**
 * Single source of truth for every account code used by the accounting engine.
 * No Action/Service should ever hardcode a literal code string — resolve
 * through this enum (and Modules\Accounting\Services\AccountResolver) instead.
 */
enum AccountCode: string
{
    // ── ASSETS ─────────────────────────────────────────────────
    case CURRENT_ASSETS = '1000';
    case CASH = '1010';
    case BANK = '1020';
    case INSURANCE_RECEIVABLE = '1030';
    case PATIENT_RECEIVABLE = '1040';
    case INVENTORY = '1050';
    case PREPAID_EXPENSES = '1060';
    case FIXED_ASSETS = '1100';
    case MEDICAL_EQUIPMENT = '1110';
    case ACCUMULATED_DEPRECIATION_MEDICAL = '1120';
    case FURNITURE = '1130';
    case COMPUTERS = '1140';

    // ── LIABILITIES ────────────────────────────────────────────
    case CURRENT_LIABILITIES = '2000';
    case DOCTOR_PAYABLE = '2010';
    case SUPPLIER_PAYABLE = '2020';
    case VAT_PAYABLE = '2030';
    case EMPLOYEE_PAYABLE = '2040';
    case PATIENT_ADVANCES = '2050';
    case LONG_TERM_LIABILITIES = '2100';
    case BANK_LOANS = '2110';

    // ── EQUITY ─────────────────────────────────────────────────
    case CAPITAL = '3010';
    case RETAINED_EARNINGS = '3020';
    case CURRENT_YEAR_NET_PROFIT = '3030';

    // ── REVENUE ────────────────────────────────────────────────
    case OPERATING_REVENUE = '4000';
    case OUTPATIENT_REVENUE = '4010';
    case LAB_REVENUE = '4020';
    case SURGERY_REVENUE = '4030';
    case LASIK_REVENUE = '4040';
    case LASER_REVENUE = '4050';
    case RETINA_REVENUE = '4060';
    case HEALTH_INSURANCE_REVENUE_OVERRIDE = '4070'; // service-level revenue_account_id override, distinct from the canonical hospital-share INSURANCE_REVENUE (4110) posted by AutoPostInsuranceClaimAction
    case PHARMACY_REVENUE = '4080';
    case INSURANCE_REVENUE_GROUP = '4100';
    case INSURANCE_REVENUE = '4110';
    case INSURANCE_REVENUE_COLLECTED = '4120'; // unused/reserved — collections reduce 1030, they are not booked as new revenue
    case OTHER_REVENUE_GROUP = '4200';
    case SUPPLIES_REVENUE = '4210';
    case MISC_REVENUE = '4220';
    case DOCTOR_SUPPLY_COST_RECOVERY = '4230';

    // ── EXPENSES ───────────────────────────────────────────────
    case DIRECT_MEDICAL_COSTS = '5000';
    case SURGERY_SUPPLIES_COST = '5010';
    case LASIK_SUPPLIES_COST = '5020';
    case MEDICINE_COST = '5030';
    case DOCTOR_EXPENSES_GROUP = '5100';
    case DOCTOR_CLINIC_EXPENSE = '5110';
    case DOCTOR_SURGERY_EXPENSE = '5120';
    case INSURANCE_DOCTOR_FEES = '5130';
    case OPERATING_EXPENSES_GROUP = '5200';
    case SALARIES = '5210';
    case RENT = '5220';
    case UTILITIES = '5230';
    case MAINTENANCE = '5240';
    case ADMIN_EXPENSE = '5250';
    case DEPRECIATION = '5260';
    case CREDIT_PURCHASE_EXPENSE = '5270';
    case BAD_DEBT = '5300';

    /**
     * Dept → default revenue account fallback, used when a service has no
     * `revenue_account_id` override. Single source of truth — also used by
     * Account::scopeModuleEnabled() to know which revenue/expense codes are
     * tied to a disable-able clinical module.
     *
     * @return array<string, self>
     */
    public static function deptRevenueMap(): array
    {
        return [
            Department::Clinic->value => self::OUTPATIENT_REVENUE,
            Department::Labs->value => self::LAB_REVENUE,
            Department::Surgery->value => self::SURGERY_REVENUE,
            Department::Lasik->value => self::LASIK_REVENUE,
            Department::Laser->value => self::LASER_REVENUE,
        ];
    }

    public static function deptRevenueCode(Department $dept): self
    {
        return self::deptRevenueMap()[$dept->value] ?? self::OUTPATIENT_REVENUE;
    }

    /**
     * Dept → doctor-share expense account (clinic-style vs surgery-style).
     */
    public static function doctorExpenseCode(Department $dept): self
    {
        return match ($dept) {
            Department::Surgery, Department::Lasik => self::DOCTOR_SURGERY_EXPENSE,
            default => self::DOCTOR_CLINIC_EXPENSE,
        };
    }

    /**
     * Stock issue / supply-consumption category → expense account.
     * Single source of truth shared by AutoPostStockIssueAction and
     * ProcessBundleSupplyAction — previously duplicated with divergent
     * (and in ProcessBundleSupplyAction's case, wrong) codes.
     */
    public static function expenseAccountForCategory(?ItemCategory $category): self
    {
        return match ($category) {
            ItemCategory::Office => self::ADMIN_EXPENSE,
            ItemCategory::Cleaning, ItemCategory::Maintenance => self::MAINTENANCE,
            default => self::SURGERY_SUPPLIES_COST,
        };
    }

    /** @return array<int, string> */
    public static function costOfServiceCodes(): array
    {
        return [self::SURGERY_SUPPLIES_COST->value, self::LASIK_SUPPLIES_COST->value, self::MEDICINE_COST->value];
    }

    /** @return array<int, string> */
    public static function doctorFeeCodes(): array
    {
        return [self::DOCTOR_CLINIC_EXPENSE->value, self::DOCTOR_SURGERY_EXPENSE->value, self::INSURANCE_DOCTOR_FEES->value];
    }

    /**
     * Parent/summary codes that must never be posted to directly.
     *
     * @return array<int, string>
     */
    public static function nonPostableCodes(): array
    {
        return [
            self::CURRENT_ASSETS->value,
            self::FIXED_ASSETS->value,
            self::CURRENT_LIABILITIES->value,
            self::LONG_TERM_LIABILITIES->value,
            self::OPERATING_REVENUE->value,
            self::INSURANCE_REVENUE_GROUP->value,
            self::OTHER_REVENUE_GROUP->value,
            self::DIRECT_MEDICAL_COSTS->value,
            self::DOCTOR_EXPENSES_GROUP->value,
            self::OPERATING_EXPENSES_GROUP->value,
        ];
    }
}
