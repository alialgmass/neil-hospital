<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Tests\TestCase;

class ChartOfAccountsV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountsSeeder::class);
    }

    public function test_every_account_code_resolves_to_a_seeded_account(): void
    {
        $resolver = app(AccountResolver::class);

        foreach (AccountCode::cases() as $code) {
            $account = $resolver->account($code);
            $this->assertSame($code->value, $account->code, "AccountCode::{$code->name} did not resolve");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $countAfterFirst = Account::count();
        $this->seed(AccountsSeeder::class);

        $this->assertSame($countAfterFirst, Account::count());
    }

    public function test_every_non_postable_master_is_flagged_and_rejected_by_journal_service(): void
    {
        $journal = app(JournalService::class);
        $cashId = app(AccountResolver::class)->id(AccountCode::CASH);

        foreach (AccountCode::nonPostableCodes() as $code) {
            $account = Account::where('code', $code)->first();
            $this->assertNotNull($account, "Non-postable code {$code} is not seeded");
            $this->assertFalse((bool) $account->is_postable, "Code {$code} should be non-postable");

            try {
                $journal->record([
                    'date' => '2026-05-01',
                    'description' => "reject {$code}",
                    'debit_account_id' => $account->id,
                    'credit_account_id' => $cashId,
                    'amount' => 100,
                    'source' => JournalSource::MANUAL,
                ]);
                $this->fail("JournalService accepted a post to non-postable {$code}");
            } catch (AccountingException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_guide_v2_key_accounts_present(): void
    {
        $this->assertDatabaseHas('accounts', ['code' => '1011', 'name' => 'خزنة التطوير — الاستقبال']);
        $this->assertDatabaseHas('accounts', ['code' => '1080']);
        $this->assertDatabaseHas('accounts', ['code' => '4060', 'name' => 'إيرادات وحدة البنتاكام']);
        $this->assertDatabaseHas('accounts', ['code' => '4130', 'name' => 'إيرادات تأمين — جراحة']);

        $contra = Account::where('code', '5115')->first();
        $this->assertSame('expenses', $contra->group->value);
        $this->assertSame('credit', $contra->nature->value);
        $this->assertSame('5100', $contra->parent->code);
    }

    public function test_liability_renumber_matches_guide(): void
    {
        $this->assertSame('2030', AccountCode::NET_SALARY_PAYABLE->value);
        $this->assertDatabaseHas('accounts', ['code' => '2030', 'group' => 'liabilities']);
        $this->assertDatabaseHas('accounts', ['code' => '2041', 'name' => 'التأمينات الاجتماعية المستحقة']);
        $this->assertDatabaseMissing('accounts', ['code' => '2040']);
    }

    public function test_inventory_is_split_into_group_and_leaf(): void
    {
        $group = Account::where('code', '1050')->first();
        $leaf = Account::where('code', '1051')->first();

        $this->assertFalse((bool) $group->is_postable);
        $this->assertTrue((bool) $leaf->is_postable);
        $this->assertSame('1050', $leaf->parent->code);
        $this->assertSame('1051', AccountCode::INVENTORY->value);
    }
}
