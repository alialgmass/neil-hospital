<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\Account;

class AccountService
{
    /**
     * Every account, with `is_group` set on parent/control accounts and
     * `rollup_balance` = own balance + every descendant's (so 2010 shows the
     * total owed to all doctors even though nothing posts to it directly).
     */
    public function all(): Collection
    {
        $accounts = Account::orderBy('code')->moduleEnabled()->get();
        $childrenOf = $accounts->groupBy('parent_id');

        $rollup = function (Account $account) use (&$rollup, $childrenOf): float {
            return (float) $account->balance + $childrenOf->get($account->id, collect())
                ->sum(fn (Account $child) => $rollup($child));
        };

        return $accounts->each(function (Account $account) use ($rollup, $childrenOf) {
            $account->setAttribute('is_group', $childrenOf->has($account->id));
            $account->setAttribute('rollup_balance', round($rollup($account), 2));
        });
    }
}
