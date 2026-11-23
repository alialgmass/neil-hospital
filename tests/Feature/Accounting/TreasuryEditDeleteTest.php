<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\TreasuryEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreasuryEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['treasury.view', 'treasury.write', 'treasury.edit', 'treasury.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['treasury.view', 'treasury.write', 'treasury.edit', 'treasury.delete']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountantRole->givePermissionTo(['treasury.view', 'treasury.write']);
        $this->accountant = User::factory()->create();
        $this->accountant->assignRole($accountantRole);
    }

    private function makeManualEntry(array $attrs = []): TreasuryEntry
    {
        return TreasuryEntry::create(array_merge([
            'type' => 'in',
            'description' => 'إيداع نقدي',
            'amount' => 500,
            'date' => today()->toDateString(),
            'source' => 'manual',
            'created_by' => $this->admin->id,
        ], $attrs));
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'in',
            'description' => 'إيداع نقدي معدَّل',
            'amount' => 700,
            'date' => today()->toDateString(),
        ], $overrides);
    }

    public function test_admin_can_update_a_manual_entry(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->admin)
            ->put("/treasury/{$entry->id}", $this->updatePayload())
            ->assertRedirect();

        $this->assertNotNull($entry->fresh()->reversed_at);
        $this->assertDatabaseHas((new TreasuryEntry)->getTable(), [
            'description' => 'إيداع نقدي معدَّل',
            'amount' => 700.00,
            'reversal_of_id' => null,
        ]);
    }

    public function test_updating_creates_an_offsetting_reversal_entry(): void
    {
        $entry = $this->makeManualEntry(['type' => 'in', 'amount' => 500]);

        $this->actingAs($this->admin)
            ->put("/treasury/{$entry->id}", $this->updatePayload())
            ->assertRedirect();

        $this->assertDatabaseHas((new TreasuryEntry)->getTable(), [
            'reversal_of_id' => $entry->id,
            'type' => 'out',
            'amount' => 500.00,
        ]);
    }

    public function test_admin_can_delete_a_manual_entry(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->admin)
            ->delete("/treasury/{$entry->id}")
            ->assertRedirect();

        $this->assertNotNull($entry->fresh()->reversed_at);
        // The original row still exists — archive preserved, not hard-deleted.
        $this->assertDatabaseHas((new TreasuryEntry)->getTable(), ['id' => $entry->id]);
        $this->assertDatabaseHas((new TreasuryEntry)->getTable(), [
            'reversal_of_id' => $entry->id,
            'type' => 'out',
            'amount' => 500.00,
        ]);
    }

    public function test_deleting_preserves_correct_balance(): void
    {
        $entry = $this->makeManualEntry(['type' => 'in', 'amount' => 500]);

        $this->actingAs($this->admin)->delete("/treasury/{$entry->id}")->assertRedirect();

        $response = $this->actingAs($this->admin)->get('/treasury');
        $response->assertInertia(fn ($page) => $page->where('balance.balance', 0));
    }

    public function test_non_manual_entry_cannot_be_updated(): void
    {
        $entry = $this->makeManualEntry(['source' => 'booking']);

        $this->actingAs($this->admin)
            ->put("/treasury/{$entry->id}", $this->updatePayload())
            ->assertSessionHasErrors('source');

        $this->assertNull($entry->fresh()->reversed_at);
    }

    public function test_non_manual_entry_cannot_be_deleted(): void
    {
        $entry = $this->makeManualEntry(['source' => 'purchase']);

        $this->actingAs($this->admin)
            ->delete("/treasury/{$entry->id}")
            ->assertSessionHasErrors('source');

        $this->assertNull($entry->fresh()->reversed_at);
    }

    public function test_already_reversed_entry_cannot_be_updated_or_deleted_again(): void
    {
        $entry = $this->makeManualEntry(['reversed_at' => now()]);

        $this->actingAs($this->admin)
            ->put("/treasury/{$entry->id}", $this->updatePayload())
            ->assertSessionHasErrors('source');

        $this->actingAs($this->admin)
            ->delete("/treasury/{$entry->id}")
            ->assertSessionHasErrors('source');
    }

    public function test_a_reversal_entry_itself_cannot_be_edited_or_deleted(): void
    {
        $original = $this->makeManualEntry();
        $reversal = $this->makeManualEntry([
            'type' => 'out',
            'source' => 'reversal',
            'reversal_of_id' => $original->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/treasury/{$reversal->id}", $this->updatePayload())
            ->assertSessionHasErrors('source');
    }

    public function test_user_without_treasury_edit_permission_gets_forbidden(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->accountant)
            ->put("/treasury/{$entry->id}", $this->updatePayload())
            ->assertForbidden();
    }

    public function test_user_without_treasury_delete_permission_gets_forbidden(): void
    {
        $entry = $this->makeManualEntry();

        $this->actingAs($this->accountant)
            ->delete("/treasury/{$entry->id}")
            ->assertForbidden();
    }

    public function test_statement_report_computes_correct_running_balance(): void
    {
        $this->makeManualEntry(['date' => '2026-01-01', 'type' => 'in', 'amount' => 1000]);
        $this->makeManualEntry(['date' => '2026-01-02', 'type' => 'out', 'amount' => 300]);
        $this->makeManualEntry(['date' => '2026-01-03', 'type' => 'in', 'amount' => 200]);

        $response = $this->actingAs($this->admin)->get('/treasury/statement');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('statement.statement.0.balance', 1000)
            ->where('statement.statement.1.balance', 700)
            ->where('statement.statement.2.balance', 900)
        );
    }
}
