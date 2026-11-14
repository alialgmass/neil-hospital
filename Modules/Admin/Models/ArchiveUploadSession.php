<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;

class ArchiveUploadSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'booking_id', 'file_name', 'mime_type', 'file_size', 'chunk_size', 'total_chunks', 'received_chunks',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
