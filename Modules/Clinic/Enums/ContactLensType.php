<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Patient's contact-lens wear.
 */
enum ContactLensType: string
{
    use HasSelectOptions;

    case None = 'none';
    case Soft = 'soft';
    case Rgp = 'rgp';
    case Toric = 'toric';
    case Cosmetic = 'cosmetic';

    public function label(): string
    {
        return match ($this) {
            self::None => 'لا يستخدم',
            self::Soft => 'لينة (Soft)',
            self::Rgp => 'صلبة (RGP)',
            self::Toric => 'توريك (Toric)',
            self::Cosmetic => 'تجميلية',
        };
    }
}
