<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;

/**
 * Centralized lookup for account codes → account records/ids. Every
 * AutoPost / Process action must go through this instead of hardcoding
 * `DB::table('accounts')->where('code', '1010')->value('id')`.
 *
 * Deliberately uncached: this is queried per journal-posting call (not a
 * hot path), and caching account ids by code is unsafe under RefreshDatabase
 * test runs where the same code gets a new row id every test.
 */
class AccountResolver
{
    public function account(AccountCode $code): Account
    {
        $account = Account::where('code', $code->value)->first();

        if (! $account) {
            throw new AccountingException("Account code {$code->value} ({$code->name}) is not seeded in the chart of accounts.");
        }

        return $account;
    }

    public function id(AccountCode $code): string
    {
        $id = Account::where('code', $code->value)->value('id');

        if (! $id) {
            throw new AccountingException("Account code {$code->value} ({$code->name}) is not seeded in the chart of accounts.");
        }

        return $id;
    }

    /**
     * Assert an account (by id) is safe to post to: exists, active, and
     * not a parent/summary account.
     */
    public function mustBePostableAndActive(string $accountId): Account
    {
        $account = Account::find($accountId);

        if (! $account) {
            throw new AccountingException("Account {$accountId} does not exist.");
        }

        if (! $account->is_active) {
            throw new AccountingException("Account {$account->code} ({$account->name}) is inactive and cannot be posted to.");
        }

        if (! $account->is_postable) {
            throw new AccountingException("Account {$account->code} ({$account->name}) is a parent/summary account and cannot be posted to directly.");
        }

        // Defense in depth: even if is_postable was left true, an account
        // with children is structurally a summary account.
        if ($account->children()->exists()) {
            throw new AccountingException("Account {$account->code} ({$account->name}) has child accounts and cannot be posted to directly.");
        }

        return $account;
    }
}
