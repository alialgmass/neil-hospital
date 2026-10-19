<?php

namespace Modules\Clinic\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog of ophthalmic diagnoses that examinations link to (with the eye
 * and optional notes stored on {@see MedicalExaminationDiagnosis}).
 */
class Diagnosis extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
