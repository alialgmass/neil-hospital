<?php

namespace Modules\Inventory\Enums;

enum ItemCategory: string
{
    case Medical = 'medical';
    case Office = 'office';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'طبي',
            self::Office => 'مكتبي',
            self::Cleaning => 'نظافة',
            self::Maintenance => 'صيانة',
            self::Other => 'أخرى',
        };
    }
}
