<?php

namespace Modules\Surgery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrBed extends Model
{
    protected $table = 'or_beds';

    protected $fillable = ['room_id', 'bed_number', 'status'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(OrRoom::class, 'room_id');
    }

    public function surgery(): HasOne
    {
        return $this->hasOne(Surgery::class, 'or_bed_id');
    }
}
