<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemModuleDeptFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'dashboard']);
        Permission::firstOrCreate(['name' => 'reports.financial']);
        Permission::firstOrCreate(['name' => 'journal.view']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['dashboard', 'reports.financial', 'journal.view']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->makeBooking('clinic');
        $this->makeBooking('lasik');
        $this->makeBooking('laser');
    }

    private function makeBooking(string $dept): void
    {
        Booking::create([
            'file_no' => 'MRN-'.strtoupper($dept),
            'patient_name' => 'مريض '.$dept,
            'dept' => $dept,
            'visit_date' => today()->toDateString(),
            'price' => 100.00,
            'discount' => 0.00,
            'ins_amount' => 0.00,
            'paid_amount' => 100.00,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_disabled_department_disappears_from_dashboard_revenue_breakdown(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/dashboard?to='.today()->addDay()->toDateString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('revenueByDept', fn ($rows) => ! collect($rows)->pluck('dept')->contains('lasik')
                && collect($rows)->pluck('dept')->contains('clinic')
                && collect($rows)->pluck('dept')->contains('laser'))
        );
    }

    public function test_disabled_department_disappears_from_dept_revenue_report(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/reports/dept-revenue?to='.today()->addDay()->toDateString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => ! collect($rows)->pluck('dept')->contains('lasik'))
        );
    }

    public function test_disabling_one_department_does_not_affect_a_sibling_department(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/reports/dept-revenue?to='.today()->addDay()->toDateString());

        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => collect($rows)->pluck('dept')->contains('laser')
                && collect($rows)->pluck('dept')->contains('clinic'))
        );
    }

    public function test_disabled_department_cost_center_disappears_from_cost_centers_report(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/cost-centers');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('centers', fn ($centers) => ! collect($centers)->pluck('code')->contains('CC-LASIK')
                && collect($centers)->pluck('code')->contains('CC-LASER'))
        );
    }

    public function test_new_booking_cannot_be_created_for_a_disabled_department(): void
    {
        Permission::firstOrCreate(['name' => 'booking.create']);
        $this->user->givePermissionTo('booking.create');

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض جديد',
            'dept' => 'lasik',
            'visit_date' => today()->toDateString(),
        ]);

        $response->assertSessionHasErrors('dept');
        $this->assertDatabaseMissing('bookings', ['dept' => 'lasik', 'patient_name' => 'مريض جديد']);
    }

    public function test_new_booking_can_still_be_created_for_an_enabled_department_while_a_sibling_is_disabled(): void
    {
        Permission::firstOrCreate(['name' => 'booking.create']);
        $this->user->givePermissionTo('booking.create');

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض جديد',
            'dept' => 'clinic',
            'visit_date' => today()->toDateString(),
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
        ]);

        $response->assertSessionDoesntHaveErrors('dept');
        $this->assertDatabaseHas('bookings', ['dept' => 'clinic', 'patient_name' => 'مريض جديد']);
    }

    public function test_disabled_department_disappears_from_the_booking_list(): void
    {
        Permission::firstOrCreate(['name' => 'booking.view']);
        $this->user->givePermissionTo('booking.view');

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('bookings.data', fn ($rows) => ! collect($rows)->pluck('dept')->contains('lasik')
                && collect($rows)->pluck('dept')->contains('clinic')
                && collect($rows)->pluck('dept')->contains('laser'))
        );
    }

    public function test_disabled_department_booking_list_returns_nothing_even_when_filtered_explicitly(): void
    {
        Permission::firstOrCreate(['name' => 'booking.view']);
        $this->user->givePermissionTo('booking.view');

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/booking?dept=lasik');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('bookings.data', fn ($rows) => count($rows) === 0));
    }

    public function test_disabled_department_revenue_account_disappears_from_chart_of_accounts(): void
    {
        Account::create(['code' => '4040', 'name' => 'إيرادات وحدة الليزك', 'group' => 'revenues', 'nature' => 'credit']);
        Account::create(['code' => '4050', 'name' => 'إيرادات الليزر', 'group' => 'revenues', 'nature' => 'credit']);

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');
        Permission::firstOrCreate(['name' => 'journal.view']);

        $response = $this->actingAs($this->user)->get('/accounts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => ! collect($accounts)->pluck('code')->contains('4040')
                && collect($accounts)->pluck('code')->contains('4050'))
        );
    }
}
