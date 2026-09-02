<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountGroup;
use Modules\Accounting\Enums\AccountNature;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    private Account $debit;

    private Account $credit;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['journal.view', 'journal.write', 'journal.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['journal.view', 'journal.write', 'journal.delete']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountantRole->givePermissionTo(['journal.view', 'journal.write']);
        $this->accountant = User::factory()->create();
        $this->accountant->assignRole($accountantRole);

        $this->debit = Account::create([
            'code' => '1010', 'name' => 'الخزنة الرئيسية',
            'group' => AccountGroup::Assets, 'nature' => AccountNature::Debit,
            'balance' => 0, 'is_active' => true, 'is_postable' => true,
        ]);
        $this->credit = Account::create([
            'code' => '4010', 'name' => 'إيرادات العيادة الخارجية (كشف)',
            'group' => AccountGroup::Revenues, 'nature' => AccountNature::Credit,
            'balance' => 0, 'is_active' => true, 'is_postable' => true,
        ]);
    }

    private function makeManualEntry(array $attrs = []): JournalEntry
    {
        // Extra attributes (source override, reversed_at, reversal_of_id, ...)
        // are applied via a raw update after posting, so account balances are
        // still adjusted exactly like a real post through JournalService.
        $overrides = array_diff_key($attrs, array_flip(['date', 'description', 'debit_account_id', 'credit_account_id', 'amount']));

        $entry = app(JournalService::class)->record(array_merge([
            'date' => today()->toDateString(),
            'description' => 'قيد يدوي',
            'debit_account_id' => $this->debit->id,
            'credit_account_id' => $this->credit->id,
            'amount' => 500,
            'source' => 'manual',
        ], array_intersect_key($attrs, array_flip(['date', 'description', 'debit_account_id', 'credit_account_id', 'amount']))));

        $entry->forceFill(['created_by' => $this->admin->id, ...$overrides])->save();

        return $entry->fresh();
    }

    public function test_admin_can_delete_a_manual_entry(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->admin)
            ->delete("/journal/{$entry->id}")
            ->assertRedirect();

        $this->assertNotNull($entry->fresh()->reversed_at);
        // Original row preserved — archive intact, not hard-deleted.
        $this->assertDatabaseHas((new JournalEntry)->getTable(), ['id' => $entry->id]);
    }

    public function test_deleting_posts_a_reversing_entry_with_swapped_accounts(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->admin)->delete("/journal/{$entry->id}")->assertRedirect();

        $this->assertDatabaseHas((new JournalEntry)->getTable(), [
            'reversal_of_id' => $entry->id,
            'debit_account_id' => $this->credit->id,
            'credit_account_id' => $this->debit->id,
            'amount' => 500.00,
        ]);
    }

    public function test_deleting_restores_account_balances(): void
    {
        $entry = $this->makeManualEntry();

        $this->assertEquals(500, (float) $this->debit->fresh()->balance);
        $this->assertEquals(500, (float) $this->credit->fresh()->balance);

        $this->actingAs($this->admin)->delete("/journal/{$entry->id}")->assertRedirect();

        $this->assertEquals(0, (float) $this->debit->fresh()->balance);
        $this->assertEquals(0, (float) $this->credit->fresh()->balance);
    }

    public function test_non_manual_entry_cannot_be_deleted(): void
    {
        $entry = $this->makeManualEntry(['source' => 'booking']);

        $this->actingAs($this->admin)
            ->delete("/journal/{$entry->id}")
            ->assertSessionHasErrors('source');

        $this->assertNull($entry->fresh()->reversed_at);
    }

    public function test_already_reversed_entry_cannot_be_deleted_again(): void
    {
        $entry = $this->makeManualEntry(['reversed_at' => now()]);

        $this->actingAs($this->admin)
            ->delete("/journal/{$entry->id}")
            ->assertSessionHasErrors('source');
    }

    public function test_a_reversal_entry_itself_cannot_be_deleted(): void
    {
        $original = $this->makeManualEntry();
        $reversal = $this->makeManualEntry([
            'source' => 'reversal',
            'reversal_of_id' => $original->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/journal/{$reversal->id}")
            ->assertSessionHasErrors('source');
    }

    public function test_user_without_journal_delete_permission_gets_forbidden(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->accountant)
            ->delete("/journal/{$entry->id}")
            ->assertForbidden();
    }
}
