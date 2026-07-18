<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Enums\CompanyStatus;

class InsuranceCompany extends Model
{
    use HasUlids;

    protected $table = 'insurance_companies';

    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'contract_no',
        'coverage_pct',
        'disc_pct',
        'contact_person',
        'email',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'coverage_pct' => 'float',
            'disc_pct' => 'float',
            'status' => CompanyStatus::class,
        ];
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'ins_company_id');
    }
}
