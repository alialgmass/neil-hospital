<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Previous ophthalmic conditions offered as quick checkboxes in the history section.
 */
enum EyeDisease: string
{
    use HasSelectOptions;

    case Glaucoma = 'glaucoma';
    case Cataract = 'cataract';
    case DiabeticRetinopathy = 'diabetic_retinopathy';
    case Keratoconus = 'keratoconus';
    case Uveitis = 'uveitis';
    case DryEye = 'dry_eye';
    case Amblyopia = 'amblyopia';
    case Strabismus = 'strabismus';
    case RetinalDetachment = 'retinal_detachment';
    case MacularDegeneration = 'macular_degeneration';

    public function label(): string
    {
        return match ($this) {
            self::Glaucoma => 'Glaucoma — المياه الزرقاء',
            self::Cataract => 'Cataract — المياه البيضاء',
            self::DiabeticRetinopathy => 'Diabetic Retinopathy — اعتلال الشبكية السكري',
            self::Keratoconus => 'Keratoconus — القرنية المخروطية',
            self::Uveitis => 'Uveitis — التهاب القزحية',
            self::DryEye => 'Dry Eye — جفاف العين',
            self::Amblyopia => 'Amblyopia — كسل العين',
            self::Strabismus => 'Strabismus — الحول',
            self::RetinalDetachment => 'Retinal Detachment — انفصال الشبكية',
            self::MacularDegeneration => 'AMD — ضمور الشبكية',
        };
    }
}
