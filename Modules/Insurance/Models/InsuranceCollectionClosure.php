<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCollectionClosure extends Model
{
    use HasUlids;

    protected $table = 'insurance_collection_closures';

    protected $fillable = ['collection_date', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['collection_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
