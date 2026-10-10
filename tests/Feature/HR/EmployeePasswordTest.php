<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\HR\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $linkedUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'users.manage']);
        Permission::firstOrCreate(['name' => 'hr.manage']);
        Role::firstOrCreate(['name' => 'admin'])->givePermissionTo(['users.manage', 'hr.manage']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->linkedUser = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->employee = $this->makeEmployee('EMP-0100', $this->linkedUser->id);
    }

    private function makeEmployee(string $employeeNo, ?int $userId): Employee
    {
        return Employee::create([
            'employee_no' => $employeeNo,
            'name' => 'موظف اختبار',
            'user_id' => $userId,
            'dept' => 'الاستقبال',
            'position' => 'موظف استقبال',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    public function test_admin_changes_employee_password(): void
    {
        $oldRememberToken = $this->linkedUser->remember_token;

        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}/password", [
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = $this->linkedUser->fresh();
        $this->assertTrue(Hash::check('new-password-456', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->actingAs($this->admin)
            ->put("/employees/{$this->employee->id}/password", [
                'password' => 'new-password-456',
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-123', $this->linkedUser->fresh()->password));
    }

    public function test_employee_without_login_account_is_rejected(): void
    {
        $employee = $this->makeEmployee('EMP-0101', null);

        $this->actingAs($this->admin)
            ->put("/employees/{$employee->id}/password", [
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_hr_manager_without_users_manage_cannot_change_password(): void
    {
        Role::firstOrCreate(['name' => 'hr'])->givePermissionTo('hr.manage');
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $this->actingAs($hrUser)
            ->put("/employees/{$this->employee->id}/password", [
                'password' => 'new-password-456',
                'password_confirmation' => 'new-password-456',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password-123', $this->linkedUser->fresh()->password));
    }
}
