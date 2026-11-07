<?php

namespace Modules\Clinic\Models;

use App\Enums\EyeSide;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalExaminationDiagnosis extends Model
{
    use HasUlids;

    protected $fillable = [
        'medical_examination_id',
        'diagnosis_id',
        'eye',
        'notes',
    ];

    protected $casts = [
        'eye' => EyeSide::class,
    ];

    public function examination(): BelongsTo
    {
        return $this->belongsTo(MedicalExamination::class, 'medical_examination_id');
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }
}
