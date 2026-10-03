<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Chart-of-accounts writes are guarded by accounting.write at both the route
 * and the FormRequest (previously the route checked journal.write).
 */
class ChartOfAccountsPermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return ['code' => '9999', 'name' => 'حساب تجريبي', 'group' => 'expenses', 'nature' => 'debit', 'is_postable' => true];
    }

    public function test_user_with_accounting_write_can_create_account(): void
    {
        $this->actingAs($this->userWithPermissions(['accounting.write']))
            ->post('/accounts', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', ['code' => '9999']);
    }

    public function test_journal_write_alone_cannot_create_or_update_accounts(): void
    {
        $user = $this->userWithPermissions(['journal.view', 'journal.write']);

        $this->actingAs($user)->post('/accounts', $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('accounts', ['code' => '9999']);

        $account = Account::create(['code' => '9998', 'name' => 'قديم', 'group' => 'expenses', 'nature' => 'debit']);

        $this->actingAs($user)
            ->putJson("/accounts/{$account->id}", ['name' => 'جديد'])
            ->assertStatus(403);

        $this->assertSame('قديم', $account->fresh()->name);
    }
}
