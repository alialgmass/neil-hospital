<?php

namespace Modules\Inventory\Enums;

enum ItemUnit: string
{
    case Piece = 'piece';
    case Box = 'box';
    case Pack = 'pack';
    case Bottle = 'bottle';
    case Pair = 'pair';
    case Gram = 'gram';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'قطعة',
            self::Box => 'صندوق',
            self::Pack => 'عبوة',
            self::Bottle => 'زجاجة',
            self::Pair => 'زوج',
            self::Gram => 'جرام',
            self::Liter => 'لتر',
        };
    }
}
