<?php

namespace Modules\Accounting\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Account;

/**
 * Shared by the two InsuranceCompany models (Modules\Booking and
 * Modules\Insurance, same underlying table) so the receivable-account
 * relation lives in one place instead of being copy-pasted onto both.
 */
trait HasReceivableAccount
{
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }
}
