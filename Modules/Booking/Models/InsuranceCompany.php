<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends Model
{
    use HasUlids;

    protected $table = 'insurance_companies';

    protected $fillable = [
        'name', 'code', 'phone', 'address', 'contract_no',
        'coverage_pct', 'disc_pct', 'contact_person', 'email', 'status', 'notes',
    ];

    protected $casts = [
        'coverage_pct' => 'decimal:2',
        'disc_pct' => 'decimal:2',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'insurance_company_id');
    }
}
