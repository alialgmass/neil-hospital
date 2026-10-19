<?php

namespace Modules\Accounting\Enums;

/**
 * Fixed-asset class → its depreciation expense (526x) and accumulated
 * depreciation contra-asset (11x1) account pair.
 */
enum AssetClass: string
{
    case Buildings = 'buildings';
    case MedicalEquipment = 'medical_equipment';
    case Furniture = 'furniture';
    case Computers = 'computers';
    case Vehicles = 'vehicles';

    public function label(): string
    {
        return match ($this) {
            self::Buildings => 'مباني',
            self::MedicalEquipment => 'أجهزة طبية',
            self::Furniture => 'أثاث وتجهيزات',
            self::Computers => 'حاسبات وأجهزة مكتبية',
            self::Vehicles => 'سيارات',
        };
    }

    public function depreciationExpenseCode(): AccountCode
    {
        return match ($this) {
            self::Buildings => AccountCode::DEPRECIATION_BUILDINGS,
            self::MedicalEquipment => AccountCode::DEPRECIATION_MEDICAL,
            self::Furniture => AccountCode::DEPRECIATION_FURNITURE,
            self::Computers => AccountCode::DEPRECIATION_COMPUTERS,
            self::Vehicles => AccountCode::DEPRECIATION_VEHICLES,
        };
    }

    public function accumulatedDepreciationCode(): AccountCode
    {
        return match ($this) {
            self::Buildings => AccountCode::ACCUMULATED_DEPRECIATION_BUILDINGS,
            self::MedicalEquipment => AccountCode::ACCUMULATED_DEPRECIATION_MEDICAL,
            self::Furniture => AccountCode::ACCUMULATED_DEPRECIATION_FURNITURE,
            self::Computers => AccountCode::ACCUMULATED_DEPRECIATION_COMPUTERS,
            self::Vehicles => AccountCode::ACCUMULATED_DEPRECIATION_VEHICLES,
        };
    }
}
