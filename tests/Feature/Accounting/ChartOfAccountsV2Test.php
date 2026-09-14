<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
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

    /** Load the anonymous-class migration under test. */
    private function restructureMigration(): object
    {
        return require database_path('migrations/2026_09_07_120000_restructure_chart_of_accounts_v2.php');
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

    public function test_renumber_migration_preserves_every_balance_and_keeps_the_ledger_footed(): void
    {
        // Rebuild a pre-v2 chart with the codes the migration relabels.
        Account::query()->delete();
        $pre = collect([
            ['1010', 'assets', 'debit'],
            ['1040', 'assets', 'debit'],   // → 1048
            ['1050', 'assets', 'debit'],   // → 1051
            ['1110', 'assets', 'debit'],   // → 1130
            ['2040', 'liabilities', 'credit'], // → 2030
            ['4230', 'revenues', 'credit'],    // → 5115 (+ reclassify)
        ])->mapWithKeys(fn ($r) => [$r[0] => Account::create([
            'code' => $r[0], 'name' => "acct {$r[0]}", 'group' => $r[1], 'nature' => $r[2], 'is_postable' => true,
        ])]);

        $journal = app(JournalService::class);
        $journal->record([
            'date' => '2026-05-03', 'description' => 'sale', 'amount' => 300,
            'debit_account_id' => $pre['1010']->id, 'credit_account_id' => $pre['4230']->id,
            'source' => JournalSource::MANUAL,
        ]);
        $journal->record([
            'date' => '2026-05-04', 'description' => 'stock', 'amount' => 120,
            'debit_account_id' => $pre['1050']->id, 'credit_account_id' => $pre['2040']->id,
            'source' => JournalSource::MANUAL,
        ]);

        $balancesById = Account::pluck('balance', 'id')->map(fn ($b) => (float) $b);
        $entryCount = JournalEntry::count();
        $debitTotal = (float) JournalEntry::sum('amount');

        $this->restructureMigration()->up();

        // Codes moved, rows (and their ids) are the same.
        $this->assertSame($pre['1040']->id, Account::where('code', '1048')->value('id'));
        $this->assertSame($pre['1050']->id, Account::where('code', '1051')->value('id'));
        $this->assertSame($pre['4230']->id, Account::where('code', '5115')->value('id'));
        $this->assertSame('expenses', Account::where('code', '5115')->value('group')->value);

        // No balance moved, no entry touched, ledger still foots.
        Account::all()->each(fn ($a) => $this->assertSame(
            $balancesById[$a->id], (float) $a->balance, "balance changed for {$a->code}"
        ));
        $this->assertSame($entryCount, JournalEntry::count());
        $this->assertSame($debitTotal, (float) JournalEntry::sum('amount'));
        $this->assertSame(
            (float) DB::table('accounts')->where('nature', 'debit')->sum('balance'),
            (float) DB::table('accounts')->where('nature', 'credit')->sum('balance'),
        );
    }
}
