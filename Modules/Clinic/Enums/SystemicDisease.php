<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Systemic conditions relevant to the eye, offered as quick checkboxes.
 */
enum SystemicDisease: string
{
    use HasSelectOptions;

    case Diabetes = 'diabetes';
    case Hypertension = 'hypertension';
    case Cardiac = 'cardiac';
    case Thyroid = 'thyroid';
    case Asthma = 'asthma';
    case Renal = 'renal';
    case Autoimmune = 'autoimmune';

    public function label(): string
    {
        return match ($this) {
            self::Diabetes => 'السكر (Diabetes)',
            self::Hypertension => 'الضغط (Hypertension)',
            self::Cardiac => 'أمراض القلب',
            self::Thyroid => 'الغدة الدرقية',
            self::Asthma => 'الربو / حساسية الصدر',
            self::Renal => 'أمراض الكلى',
            self::Autoimmune => 'أمراض مناعية / روماتيزمية',
        };
    }
}
