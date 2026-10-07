<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'users.manage']);
        Permission::firstOrCreate(['name' => 'booking.view']);
        Permission::firstOrCreate(['name' => 'insurance.view']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('users.manage');
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $this->actingAs($this->admin)
            ->post('/roles', [
                'name' => 'مشرف الاستقبال',
                'permissions' => ['booking.view', 'insurance.view'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $role = Role::where('name', 'مشرف الاستقبال')->first();

        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['booking.view', 'insurance.view'],
            $role->permissions->pluck('name')->all()
        );
    }

    public function test_role_can_be_created_without_permissions(): void
    {
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'دور فارغ'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'دور فارغ']);
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        Role::create(['name' => 'accountant', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->post('/roles', ['name' => 'accountant'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Role::where('name', 'accountant')->count());
    }

    public function test_name_is_required_and_permissions_must_exist(): void
    {
        $this->actingAs($this->admin)
            ->post('/roles', ['name' => '', 'permissions' => ['not.a.permission']])
            ->assertSessionHasErrors(['name', 'permissions.0']);
    }

    public function test_user_without_manage_permission_cannot_create_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/roles', ['name' => 'دور'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'دور']);
    }
}
