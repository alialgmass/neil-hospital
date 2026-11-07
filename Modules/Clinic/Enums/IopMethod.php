<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * How the intraocular pressure was measured.
 */
enum IopMethod: string
{
    use HasSelectOptions;

    case AirPuff = 'air_puff';
    case Applanation = 'applanation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AirPuff => 'Air Puff (Non-contact)',
            self::Applanation => 'Applanation (Goldmann)',
            self::Other => 'أخرى (Other)',
        };
    }
}
