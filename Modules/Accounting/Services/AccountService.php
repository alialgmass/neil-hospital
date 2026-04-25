<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\Account;

class AccountService
{
    public function all(): Collection
    {
        return Account::orderBy('code')->get();
    }
}
