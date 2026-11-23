<?php

namespace Modules\Clinic\Models;

use App\Enums\EyeSide;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Findings for a single eye (OD or OS) of a medical examination:
 * visual acuity, refraction, anterior segment, IOP and fundus.
 */
class MedicalExaminationEye extends Model
{
    use HasUlids;

    /** Per-eye fields accepted from the examination form. */
    public const FIELDS = [
        'ucva',
        'cva',
        'near_vision',
        'sphere',
        'cylinder',
        'axis',
        'add_power',
        'bcva',
        'eyelids',
        'conjunctiva',
        'sclera',
        'cornea',
        'anterior_chamber',
        'iris',
        'pupil',
        'lens',
        'iop',
        'optic_disc',
        'cd_ratio',
        'macula',
        'retina',
        'vessels',
        'vitreous',
    ];

    protected $fillable = [
        'medical_examination_id',
        'eye',
        ...self::FIELDS,
    ];

    protected $casts = [
        'eye' => EyeSide::class,
        'sphere' => 'decimal:2',
        'cylinder' => 'decimal:2',
        'axis' => 'integer',
        'add_power' => 'decimal:2',
        'iop' => 'decimal:1',
        'cd_ratio' => 'decimal:2',
    ];

    public function examination(): BelongsTo
    {
        return $this->belongsTo(MedicalExamination::class, 'medical_examination_id');
    }
}
