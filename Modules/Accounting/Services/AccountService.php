<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\Account;
use Modules\Admin\Enums\SystemModule;

class AccountService
{
    /** Revenue/expense accounts tied to a disable-able clinical department. */
    private const DEPT_ACCOUNTS = [
        '4010' => SystemModule::Clinic,
        '4020' => SystemModule::Labs,
        '4030' => SystemModule::Surgery,
        '4040' => SystemModule::Lasik,
        '4050' => SystemModule::Laser,
        '5020' => SystemModule::Lasik,
    ];

    public function all(): Collection
    {
        return Account::orderBy('code')
            ->get()
            ->reject(fn (Account $account) => isset(self::DEPT_ACCOUNTS[$account->code])
                && ! self::DEPT_ACCOUNTS[$account->code]->isEnabled())
            ->values();
    }
}
