<?php

namespace Modules\Clinic\Enums;

use Modules\Clinic\Concerns\HasSelectOptions;

/**
 * Previous eye operations offered as quick checkboxes in the history section.
 */
enum EyeSurgery: string
{
    use HasSelectOptions;

    case Cataract = 'cataract';
    case Refractive = 'refractive';
    case Glaucoma = 'glaucoma';
    case Retinal = 'retinal';
    case CornealTransplant = 'corneal_transplant';
    case CrossLinking = 'cross_linking';
    case Strabismus = 'strabismus';
    case IntravitrealInjection = 'intravitreal_injection';

    public function label(): string
    {
        return match ($this) {
            self::Cataract => 'Cataract / Phaco — مياه بيضاء',
            self::Refractive => 'LASIK / PRK — تصحيح إبصار',
            self::Glaucoma => 'Glaucoma surgery — مياه زرقاء',
            self::Retinal => 'Vitreoretinal — شبكية',
            self::CornealTransplant => 'Keratoplasty — زرع قرنية',
            self::CrossLinking => 'Cross-linking — تثبيت القرنية',
            self::Strabismus => 'Strabismus surgery — حول',
            self::IntravitrealInjection => 'Intravitreal injection — حقن داخل العين',
        };
    }
}
