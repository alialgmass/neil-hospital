<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\HR\Enums\PayrollStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeDeduction;
use Modules\HR\Models\Payroll;
use Modules\HR\Services\HRService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'hr.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hr.view', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'hr_manager', 'guard_name' => 'web']);
        $role->givePermissionTo(['hr.manage', 'hr.view']);
        $this->manager = User::factory()->create();
        $this->manager->assignRole($role);

        // 5,200 ÷ default 26 working days = 200 per day.
        $this->employee = Employee::create([
            'employee_no' => 'EMP-0900',
            'name' => 'موظف خصومات',
            'dept' => 'الاستقبال',
            'position' => 'موظف استقبال',
            'hire_date' => '2026-01-01',
            'base_salary' => 5200,
            'allowances' => 0,
            'contract_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    private function makePayroll(PayrollStatus $status = PayrollStatus::Draft): Payroll
    {
        return Payroll::create([
            'employee_id' => $this->employee->id,
            'month' => 10,
            'year' => 2026,
            'base_salary' => 5200,
            'allowances' => 0,
            'overtime_pay' => 0,
            'deductions' => 0,
            'net_salary' => 5200,
            'status' => $status->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'deduction_date' => '2026-10-05',
            'type' => 'days',
            'days' => 2,
            'reason' => 'غياب بدون إذن',
        ], $overrides);
    }

    public function test_day_based_deduction_is_priced_from_employee_salary(): void
    {
        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload())
            ->assertSessionHasNoErrors();

        $deduction = EmployeeDeduction::firstOrFail();

        $this->assertSame('days', $deduction->type->value);
        $this->assertEquals(200.0, (float) $deduction->daily_rate);
        $this->assertEquals(400.0, (float) $deduction->amount);
        $this->assertSame('غياب بدون إذن', $deduction->reason);
    }

    public function test_amount_based_deduction_uses_the_entered_amount(): void
    {
        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload(['type' => 'amount', 'amount' => 150.5, 'days' => null]))
            ->assertSessionHasNoErrors();

        $deduction = EmployeeDeduction::firstOrFail();

        $this->assertEquals(150.5, (float) $deduction->amount);
        $this->assertNull($deduction->days);
        $this->assertNull($deduction->daily_rate);
    }

    public function test_deduction_is_applied_to_the_draft_payroll_and_removed_with_it(): void
    {
        $payroll = $this->makePayroll();

        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload());

        $payroll->refresh();
        $this->assertEquals(400.0, (float) $payroll->deductions);
        $this->assertEquals(4800.0, (float) $payroll->net_salary);

        $this->actingAs($this->manager)
            ->delete('/employee-deductions/'.EmployeeDeduction::firstOrFail()->id)
            ->assertSessionHasNoErrors();

        $payroll->refresh();
        $this->assertEquals(0.0, (float) $payroll->deductions);
        $this->assertEquals(5200.0, (float) $payroll->net_salary);
        $this->assertSame(0, EmployeeDeduction::count());
    }

    public function test_deduction_is_rejected_when_the_months_payroll_is_approved(): void
    {
        $this->makePayroll(PayrollStatus::Approved);

        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload())
            ->assertSessionHasErrors('deduction_date');

        $this->assertSame(0, EmployeeDeduction::count());
    }

    public function test_deletion_is_rejected_when_the_months_payroll_is_approved(): void
    {
        $payroll = $this->makePayroll();
        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload());
        $payroll->update(['status' => PayrollStatus::Approved->value]);

        $this->actingAs($this->manager)
            ->delete('/employee-deductions/'.EmployeeDeduction::firstOrFail()->id)
            ->assertSessionHasErrors('deduction_date');

        $this->assertSame(1, EmployeeDeduction::count());
    }

    public function test_generated_payroll_includes_the_months_manual_deductions(): void
    {
        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload());
        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload([
            'type' => 'amount', 'amount' => 100, 'days' => null, 'deduction_date' => '2026-10-20',
        ]));
        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload([
            'type' => 'amount', 'amount' => 999, 'days' => null, 'deduction_date' => '2026-11-02',
        ]));

        app(HRService::class)->generatePayroll(10, 2026);

        $payroll = Payroll::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertEquals(500.0, (float) $payroll->deductions);
        $this->assertEquals(4700.0, (float) $payroll->net_salary);
    }

    public function test_validation_requires_days_or_amount_matching_the_type_and_a_reason(): void
    {
        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload(['days' => null, 'reason' => '']))
            ->assertSessionHasErrors(['days', 'reason']);

        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload(['type' => 'amount', 'amount' => null]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($this->manager)
            ->post('/employee-deductions', $this->payload(['days' => 0]))
            ->assertSessionHasErrors('days');
    }

    public function test_index_lists_deductions_with_totals(): void
    {
        $this->actingAs($this->manager)->post('/employee-deductions', $this->payload());

        $this->actingAs($this->manager)
            ->get('/employee-deductions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('hr/Deductions')
                ->has('deductions.data', 1)
                ->where('totals.count', 1)
                ->where('totals.amount', 400)
                ->where('workingDays', 26));
    }

    public function test_page_and_actions_require_hr_manage_permission(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get('/employee-deductions')->assertForbidden();
        $this->actingAs($viewer)->post('/employee-deductions', $this->payload())->assertForbidden();
    }
}
