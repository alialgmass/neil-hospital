<?php

namespace Tests\Feature\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\JournalService;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(array $attrs = []): Account
    {
        return Account::create(array_merge([
            'code' => uniqid('ACC'),
            'name' => 'Test Account',
            'group' => AccountGroup::Assets,
            'nature' => AccountNature::Debit,
            'balance' => 0,
            'is_active' => true,
        ], $attrs));
    }

    public function test_record_creates_journal_entry_and_adjusts_balances(): void
    {
        $this->actingAs(User::factory()->create());

        $debitAccount  = $this->makeAccount(['nature' => AccountNature::Debit,  'group' => AccountGroup::Assets]);
        $creditAccount = $this->makeAccount(['nature' => AccountNature::Credit, 'group' => AccountGroup::Liabilities]);

        $service = app(JournalService::class);
        $entry = $service->record([
            'date' => '2026-05-20',
            'description' => 'Test entry',
            'debit_account_id' => $debitAccount->id,
            'credit_account_id' => $creditAccount->id,
            'amount' => 500.00,
            'source' => 'manual',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'amount' => 500.00,
            'debit_account_id' => $debitAccount->id,
            'credit_account_id' => $creditAccount->id,
        ]);

        // Debit account (debit-nature): balance increases by amount
        $this->assertEquals(500.00, (float) $debitAccount->fresh()->balance);
        // Credit account (credit-nature): balance increases by amount
        $this->assertEquals(500.00, (float) $creditAccount->fresh()->balance);
    }

    public function test_record_decreases_balance_when_opposite_side_posted(): void
    {
        $this->actingAs(User::factory()->create());

        // Revenue account is credit-nature; debiting it reduces its balance
        $revenueAccount = $this->makeAccount(['nature' => AccountNature::Credit, 'group' => AccountGroup::Revenues, 'balance' => 1000]);
        $cashAccount    = $this->makeAccount(['nature' => AccountNature::Debit,  'group' => AccountGroup::Assets,   'balance' => 0]);

        $service = app(JournalService::class);
        $service->record([
            'date' => '2026-05-20',
            'description' => 'Reversal',
            'debit_account_id' => $revenueAccount->id,
            'credit_account_id' => $cashAccount->id,
            'amount' => 200.00,
            'source' => 'reversal',
        ]);

        // Revenue debited → balance decreases
        $this->assertEquals(800.00, (float) $revenueAccount->fresh()->balance);
        // Cash credited → cash (debit-nature) balance decreases
        $this->assertEquals(-200.00, (float) $cashAccount->fresh()->balance);
    }

    public function test_accounts_excludes_parent_accounts(): void
    {
        $parent = $this->makeAccount(['code' => '1000', 'name' => 'Assets Parent']);
        $child1 = $this->makeAccount(['code' => '1010', 'name' => 'Cash', 'parent_id' => $parent->id]);
        $child2 = $this->makeAccount(['code' => '1020', 'name' => 'Bank', 'parent_id' => $parent->id]);

        $service = app(JournalService::class);
        $ids = $service->accounts()->pluck('id');

        $this->assertNotContains($parent->id, $ids);
        $this->assertContains($child1->id, $ids);
        $this->assertContains($child2->id, $ids);
    }

    public function test_accounts_excludes_inactive_accounts(): void
    {
        $active   = $this->makeAccount(['is_active' => true]);
        $inactive = $this->makeAccount(['is_active' => false]);

        $service = app(JournalService::class);
        $ids = $service->accounts()->pluck('id');

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_total_amount_sums_filtered_entries(): void
    {
        $this->actingAs(User::factory()->create());

        $debit  = $this->makeAccount(['nature' => AccountNature::Debit]);
        $credit = $this->makeAccount(['nature' => AccountNature::Credit]);

        $service = app(JournalService::class);

        $service->record(['date' => '2026-05-01', 'description' => 'A', 'debit_account_id' => $debit->id, 'credit_account_id' => $credit->id, 'amount' => 300, 'source' => 'manual']);
        $service->record(['date' => '2026-05-15', 'description' => 'B', 'debit_account_id' => $debit->id, 'credit_account_id' => $credit->id, 'amount' => 700, 'source' => 'manual']);
        $service->record(['date' => '2026-06-01', 'description' => 'C', 'debit_account_id' => $debit->id, 'credit_account_id' => $credit->id, 'amount' => 999, 'source' => 'manual']);

        $this->assertEquals(1000.00, $service->totalAmount(['from' => '2026-05-01', 'to' => '2026-05-31']));
        $this->assertEquals(1999.00, $service->totalAmount([]));
    }

    public function test_store_journal_entry_via_http_returns_arabic_validation_messages(): void
    {
        $permission = \Spatie\Permission\Models\Permission::create(['name' => 'journal.write', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $response = $this->actingAs($user)->post('/journal', []);

        $response->assertSessionHasErrors(['date', 'description', 'debit_account_id', 'credit_account_id', 'amount']);
        $this->assertStringContainsString('مطلوب', session('errors')->first('date'));
    }
}
