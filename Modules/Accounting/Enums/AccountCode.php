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
    // Numbering follows الدليل المحاسبي v2.0 (الإصدار 2.0 — أغسطس 2026).
    case CURRENT_ASSETS = '1000';
    case CASH = '1010';
    case DEVELOPMENT_FUND = '1011'; // خزنة التطوير — الاستقبال: 50 ج من كل خدمة نقدية
    case BANK = '1020';
    case INSURANCE_RECEIVABLE = '1030'; // مجمع الذمم — التفصيل لكل جهة تعاقد بالأكواد 1031–1047
    case PATIENT_RECEIVABLE = '1048';
    case STAFF_ADVANCES = '1049';
    case INVENTORY_GROUP = '1050'; // مجمع المخزون — لا يُرحّل
    case INVENTORY = '1051'; // مخزون — مستلزمات طبية
    case MEDICINE_INVENTORY = '1052';
    case OPERATIONAL_SUPPLIES_INVENTORY = '1053';
    case PREPAID_EXPENSES = '1060';
    case WITHHOLDING_TAX_PREPAID = '1080'; // ضريبة دخل مخصومة من المنبع — أصل قابل للاسترداد
    case CENTER_CASH_CLINIC = '1090';
    case CENTER_CASH_LAB = '1091';
    case CENTER_CASH_PENTACAM = '1092';
    case CENTER_CASH_LASER = '1093';
    case CENTER_CASH_LASIK = '1094';
    case CENTER_CASH_SURGERY = '1095';
    case CENTER_CASH_INSURANCE = '1096';
    case FIXED_ASSETS = '1100';
    case LAND = '1110';
    case BUILDINGS = '1120';
    case ACCUMULATED_DEPRECIATION_BUILDINGS = '1121';
    case MEDICAL_EQUIPMENT = '1130';
    case ACCUMULATED_DEPRECIATION_MEDICAL = '1131';
    case FURNITURE = '1140';
    case ACCUMULATED_DEPRECIATION_FURNITURE = '1141';
    case COMPUTERS = '1150';
    case ACCUMULATED_DEPRECIATION_COMPUTERS = '1151';
    case VEHICLES = '1160';
    case ACCUMULATED_DEPRECIATION_VEHICLES = '1161';

    // ── LIABILITIES ────────────────────────────────────────────
    case CURRENT_LIABILITIES = '2000';
    case DOCTOR_PAYABLE = '2010'; // مجمع — التفصيل لكل طبيب بالأكواد 2201–2235
    case SUPPLIER_PAYABLE = '2020'; // مجمع — التفصيل لكل مورد بالأكواد 2301–2324
    case NET_SALARY_PAYABLE = '2030'; // مجمع مستحقات الموظفين — التفصيل بالأكواد 2401–2416
    case SOCIAL_INSURANCE_PAYABLE = '2041';
    case PATIENT_ADVANCES = '2050';
    case ACCRUED_EXPENSES = '2060';
    case INCOME_TAX_PAYABLE = '2070';
    case LONG_TERM_LIABILITIES = '2100';
    case BANK_LOANS = '2110';

    // ── EQUITY ─────────────────────────────────────────────────
    case CAPITAL = '3010';
    case RETAINED_EARNINGS = '3020';
    case CURRENT_YEAR_NET_PROFIT = '3030';
    case OWNER_DRAWINGS = '3040';

    // ── REVENUE ────────────────────────────────────────────────
    case OPERATING_REVENUE = '4000';
    case OUTPATIENT_REVENUE = '4010';
    case LAB_REVENUE = '4020';
    case SURGERY_REVENUE = '4030';
    case LASIK_REVENUE = '4040';
    case LASER_REVENUE = '4050';
    case PENTACAM_REVENUE = '4060';
    case SUPPLIES_SALE_REVENUE = '4070'; // إيراد بيع مستهلكات للأطباء (فرق سعر البيع عن الشراء)
    case PHARMACY_REVENUE = '4080';
    case INSURANCE_REVENUE_GROUP = '4100';
    case INSURANCE_REVENUE = '4110'; // إيرادات تأمين — عيادة / حصة المستشفى (افتراضي حتى تفصيل الأقسام — تذكرة 06)
    case INSURANCE_LAB_REVENUE = '4120';
    case INSURANCE_SURGERY_REVENUE = '4130';
    case INSURANCE_LASIK_REVENUE = '4140';
    case INSURANCE_LASER_REVENUE = '4150';
    case OTHER_REVENUE_GROUP = '4200';
    case SUPPLIES_REVENUE = '4210'; // بيع مستلزمات حقيقي للمريض
    case MISC_REVENUE = '4220';
    case ASSET_SALE_GAIN = '4250';
    case CONTRA_REVENUE_GROUP = '4900';
    case PATIENT_DISCOUNTS = '4910';
    case REVENUE_REFUNDS = '4920';

    // ── EXPENSES ───────────────────────────────────────────────
    case DIRECT_MEDICAL_COSTS = '5000';
    case SURGERY_SUPPLIES_COST = '5010';
    case LASIK_SUPPLIES_COST = '5020';
    case MEDICINE_COST = '5030';
    case LAB_SUPPLIES_COST = '5040';
    case DOCTOR_EXPENSES_GROUP = '5100';
    case DOCTOR_CLINIC_EXPENSE = '5110';
    case SUPPLY_COST_RECOVERED_FROM_DOCTOR = '5115'; // (-) استرداد تكلفة مستلزمات من الطبيب — حساب مقابل يخفض صافي الأتعاب
    case DOCTOR_SURGERY_EXPENSE = '5120';
    case INSURANCE_DOCTOR_FEES = '5130';
    case VISITING_DOCTOR_FEES = '5140';
    case OPERATING_EXPENSES_GROUP = '5200';
    case SALARIES = '5210';
    case HOSPITAL_SOCIAL_INSURANCE = '5211';
    case BONUSES = '5212';
    case ALLOWANCES = '5213';
    case TRAINING = '5214';
    case RENT = '5220';
    case BUILDING_MAINTENANCE = '5225';
    case UTILITIES = '5230';
    case COMMUNICATIONS = '5231';
    case MAINTENANCE = '5240';
    case OFFICE_EQUIPMENT_MAINTENANCE = '5241';
    case ADMIN_EXPENSE = '5250';
    case CLEANING_EXPENSE = '5251';
    case MARKETING_EXPENSE = '5252';
    case DEPRECIATION = '5260'; // مجمع الاستهلاك — لا يُرحّل
    case DEPRECIATION_BUILDINGS = '5261';
    case DEPRECIATION_MEDICAL = '5262';
    case DEPRECIATION_FURNITURE = '5263';
    case DEPRECIATION_COMPUTERS = '5264';
    case DEPRECIATION_VEHICLES = '5265';
    case TRANSPORT_EXPENSE = '5270';
    case BAD_DEBT = '5300';
    case ASSET_SALE_LOSS = '5310';
    case LEGAL_CONSULTING = '5330';
    case SUBSCRIPTIONS_LICENSES = '5340';

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
            Department::Pentacam->value => self::PENTACAM_REVENUE,
        ];
    }

    public static function deptRevenueCode(Department $dept): self
    {
        return self::deptRevenueMap()[$dept->value] ?? self::OUTPATIENT_REVENUE;
    }

    /**
     * Dept → insurance revenue account (4110–4150). Insurance revenue is
     * split by department the same way cash revenue is — it must never all
     * land on 4110 (Clinic's insurance revenue account) regardless of dept.
     *
     * @return array<string, self>
     */
    public static function insuranceRevenueMap(): array
    {
        return [
            Department::Clinic->value => self::INSURANCE_REVENUE,
            Department::Labs->value => self::INSURANCE_LAB_REVENUE,
            Department::Surgery->value => self::INSURANCE_SURGERY_REVENUE,
            Department::Lasik->value => self::INSURANCE_LASIK_REVENUE,
            Department::Laser->value => self::INSURANCE_LASER_REVENUE,
        ];
    }

    public static function insuranceRevenueCode(Department $dept): self
    {
        return self::insuranceRevenueMap()[$dept->value] ?? self::INSURANCE_REVENUE;
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
        return [
            self::SURGERY_SUPPLIES_COST->value,
            self::LASIK_SUPPLIES_COST->value,
            self::MEDICINE_COST->value,
            self::LAB_SUPPLIES_COST->value,
        ];
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
            self::INSURANCE_RECEIVABLE->value, // roll-up only — claims post to a company's 1031–1047 sub-account
            self::INVENTORY_GROUP->value,
            self::FIXED_ASSETS->value,
            self::CURRENT_LIABILITIES->value,
            self::LONG_TERM_LIABILITIES->value,
            self::OPERATING_REVENUE->value,
            self::INSURANCE_REVENUE_GROUP->value,
            self::OTHER_REVENUE_GROUP->value,
            self::CONTRA_REVENUE_GROUP->value,
            self::DIRECT_MEDICAL_COSTS->value,
            self::DOCTOR_EXPENSES_GROUP->value,
            self::OPERATING_EXPENSES_GROUP->value,
            self::DEPRECIATION->value,
        ];
    }
}
