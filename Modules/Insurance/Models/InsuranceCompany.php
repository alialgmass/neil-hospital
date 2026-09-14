<?php

namespace Modules\Insurance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Account;
use Modules\Insurance\Enums\CompanyStatus;

class InsuranceCompany extends Model
{
    use HasUlids;

    protected $table = 'insurance_companies';

    protected $fillable = [
        'name',
        'code',
        'receivable_account_id',
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

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }
}
