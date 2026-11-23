<?php

namespace Modules\Insurance\Models;

use App\Models\User;
use Database\Factories\PatientCashEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Booking\Models\Booking;

class PatientCashEntry extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'patient_cash_entries';

    protected static function newFactory(): PatientCashEntryFactory
    {
        return PatientCashEntryFactory::new();
    }

    protected $fillable = ['booking_id', 'patient_id', 'insurance_company_id', 'description', 'department', 'amount', 'entry_date', 'status', 'collected_by', 'collected_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'entry_date' => 'date', 'collected_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
