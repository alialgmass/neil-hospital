<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Lifecycle of a medical examination: drafts are editable, finalized ones are read-only.
 */
enum ExaminationStatus: string
{
    use HasSelectOptions;

    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Finalized => 'معتمد',
        };
    }
}
