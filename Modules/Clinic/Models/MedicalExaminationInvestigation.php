<?php

namespace Modules\Clinic\Models;

use App\Enums\EyeSide;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Service;

/**
 * An investigation (OCT, visual field, biometry…) requested during an
 * examination. It points at an existing labs/pentacam {@see Service}; the
 * name is snapshotted so the record survives a later service rename/delete.
 */
class MedicalExaminationInvestigation extends Model
{
    use HasUlids;

    protected $fillable = [
        'medical_examination_id',
        'service_id',
        'name',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
