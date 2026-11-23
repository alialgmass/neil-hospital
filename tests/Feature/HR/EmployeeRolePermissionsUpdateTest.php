<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeRolePermissionsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'hr.manage']);
        Permission::firstOrCreate(['name' => 'booking.view']);
        Permission::firstOrCreate(['name' => 'insurance.view']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo('hr.manage');
        Role::firstOrCreate(['name' => 'reception']);
        Role::firstOrCreate(['name' => 'accountant']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $linkedUser = User::factory()->create();
        $linkedUser->assignRole('reception');

        $this->employee = Employee::create([
            'employee_no' => 'EMP-0100',
            'name' => 'موظف اختبار',
            'user_id' => $linkedUser->id,
            'dept' => 'الاستقبال',
            'position' => 'موظف استقبال',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->employee->name,
            'dept' => $this->employee->dept,
            'position' => $this->employee->position,
            'hire_date' => $this->employee->hire_date,
            'contract_type' => 'full_time',
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_can_change_employee_role(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['role' => 'accountant']))
            ->assertRedirect();

        $user = $this->employee->fresh()->user->fresh();
        $this->assertTrue($user->hasRole('accountant'));
        $this->assertFalse($user->hasRole('reception'));
    }

    public function test_admin_can_add_a_direct_permission(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['permissions' => ['insurance.view']]))
            ->assertRedirect();

        $user = $this->employee->fresh()->user->fresh();
        $this->assertTrue($user->hasPermissionTo('insurance.view'));
    }

    public function test_admin_can_remove_a_direct_permission(): void
    {
        $user = $this->employee->user;
        $user->givePermissionTo('insurance.view');

        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['permissions' => []]))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->hasPermissionTo('insurance.view'));
    }

    public function test_changing_role_does_not_wipe_existing_direct_permissions(): void
    {
        $user = $this->employee->user;
        $user->givePermissionTo('insurance.view');

        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['role' => 'accountant']))
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('accountant'));
        $this->assertTrue($fresh->hasDirectPermission('insurance.view'));
    }

    public function test_updating_direct_permissions_does_not_change_role(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['permissions' => ['booking.view']]))
            ->assertRedirect();

        $fresh = $this->employee->fresh()->user->fresh();
        $this->assertTrue($fresh->hasRole('reception'));
        $this->assertTrue($fresh->hasPermissionTo('booking.view'));
    }

    public function test_user_without_hr_manage_gets_403(): void
    {
        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['role' => 'accountant']))
            ->assertForbidden();
    }

    public function test_nonexistent_permission_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['permissions' => ['does.not.exist']]))
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_nonexistent_role_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}", $this->updatePayload(['role' => 'no_such_role']))
            ->assertSessionHasErrors('role');
    }
}
