<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Patient's current spectacle wear.
 */
enum GlassesUsage: string
{
    use HasSelectOptions;

    case None = 'none';
    case Distance = 'distance';
    case Near = 'near';
    case Bifocal = 'bifocal';
    case Progressive = 'progressive';

    public function label(): string
    {
        return match ($this) {
            self::None => 'لا يستخدم',
            self::Distance => 'للبعيد',
            self::Near => 'للقريب (قراءة)',
            self::Bifocal => 'ثنائية البؤرة',
            self::Progressive => 'متعددة البؤر',
        };
    }
}
