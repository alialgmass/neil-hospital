<?php

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\AccountResolver;
use Tests\TestCase;

class AccountResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_throws_for_unseeded_code(): void
    {
        $this->expectException(AccountingException::class);

        app(AccountResolver::class)->id(AccountCode::CASH);
    }

    public function test_id_resolves_seeded_code(): void
    {
        $account = Account::create([
            'code' => '1010', 'name' => 'الخزنة', 'group' => 'assets', 'nature' => 'debit',
        ]);

        $this->assertSame($account->id, app(AccountResolver::class)->id(AccountCode::CASH));
    }

    public function test_must_be_postable_and_active_rejects_non_postable_account(): void
    {
        $parent = Account::create([
            'code' => '1000', 'name' => 'الأصول المتداولة', 'group' => 'assets', 'nature' => 'debit',
            'is_postable' => false,
        ]);

        $this->expectException(AccountingException::class);

        app(AccountResolver::class)->mustBePostableAndActive($parent->id);
    }

    public function test_must_be_postable_and_active_rejects_account_with_children_even_if_flagged_postable(): void
    {
        $parent = Account::create([
            'code' => '1000', 'name' => 'الأصول المتداولة', 'group' => 'assets', 'nature' => 'debit',
            'is_postable' => true, // deliberately misconfigured
        ]);
        Account::create([
            'code' => '1010', 'name' => 'الخزنة', 'group' => 'assets', 'nature' => 'debit',
            'parent_id' => $parent->id,
        ]);

        $this->expectException(AccountingException::class);

        app(AccountResolver::class)->mustBePostableAndActive($parent->id);
    }

    public function test_must_be_postable_and_active_rejects_inactive_account(): void
    {
        $account = Account::create([
            'code' => '1010', 'name' => 'الخزنة', 'group' => 'assets', 'nature' => 'debit',
            'is_active' => false,
        ]);

        $this->expectException(AccountingException::class);

        app(AccountResolver::class)->mustBePostableAndActive($account->id);
    }

    public function test_must_be_postable_and_active_accepts_valid_leaf_account(): void
    {
        $account = Account::create([
            'code' => '1010', 'name' => 'الخزنة', 'group' => 'assets', 'nature' => 'debit',
        ]);

        $resolved = app(AccountResolver::class)->mustBePostableAndActive($account->id);

        $this->assertSame($account->id, $resolved->id);
    }
}
