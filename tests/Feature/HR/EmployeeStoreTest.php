<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'hr.manage']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('hr.manage');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_store_employee(): void
    {
        $response = $this->actingAs($this->admin)->post('/employees', [
            'name' => 'أحمد محمد',
            'dept' => 'التمريض',
            'position' => 'ممرض',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['name' => 'أحمد محمد']);
    }

    public function test_store_creates_linked_user_with_role_when_email_is_provided(): void
    {
        $staffRole = Role::firstOrCreate(['name' => 'reception']);

        $response = $this->actingAs($this->admin)->post('/employees', [
            'employee_no' => 'EMP-0099',
            'name' => 'سارة موظفة',
            'username' => 'sara.employee',
            'email' => 'sara@example.com',
            'password' => 'secret-password',
            'role' => $staffRole->name,
            'dept' => 'الاستقبال',
            'position' => 'موظفة استقبال',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'sara@example.com']);

        $employee = Employee::where('email', 'sara@example.com')->firstOrFail();
        $this->assertNotNull($employee->user_id);
        $this->assertSame('sara.employee', $employee->user->username);
        $this->assertTrue($employee->user->hasRole('reception'));
    }

    public function test_store_defaults_salary_to_zero_when_empty(): void
    {
        $response = $this->actingAs($this->admin)->post('/employees', [
            'name' => 'فاطمة علي',
            'dept' => 'الإدارة',
            'position' => 'سكرتيرة',
            'hire_date' => '2026-01-01',
            'base_salary' => '',
            'allowances' => '',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['name' => 'فاطمة علي', 'base_salary' => 0]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->admin)->post('/employees', [
            'dept' => 'التمريض',
            'position' => 'ممرض',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_requires_username_when_password_is_provided(): void
    {
        $response = $this->actingAs($this->admin)->post('/employees', [
            'name' => 'خالد موظف',
            'email' => 'khaled@example.com',
            'password' => 'secret-password',
            'dept' => 'الإدارة',
            'position' => 'محاسب',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('users', ['email' => 'khaled@example.com']);
    }

    public function test_store_rejects_duplicate_username(): void
    {
        User::create([
            'name' => 'مستخدم موجود',
            'username' => 'taken.user',
            'email' => 'taken@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->actingAs($this->admin)->post('/employees', [
            'employee_no' => 'EMP-0100',
            'name' => 'آخر موظف',
            'username' => 'taken.user',
            'password' => 'secret-password',
            'role' => 'reception',
            'dept' => 'الاستقبال',
            'position' => 'موظف استقبال',
            'hire_date' => '2026-01-01',
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('username');
    }
}
