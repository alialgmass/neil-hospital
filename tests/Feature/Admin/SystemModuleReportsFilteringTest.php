<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
use Modules\Booking\Models\Booking;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\States\ScheduledState;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemModuleReportsFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'reports.clinical', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'reports.financial', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['reports.clinical', 'reports.financial']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->makeBooking('clinic');
        $this->makeBooking('lasik');
        $this->makeBooking('surgery');
    }

    private function makeBooking(string $dept): Booking
    {
        return Booking::create([
            'file_no' => 'MRN-'.strtoupper($dept).'-'.uniqid(),
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

    public function test_cases_report_hides_disabled_module_rows(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/reports/cases?from='.today()->subDay()->toDateString().'&to='.today()->addDay()->toDateString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => ! collect($rows)->pluck('dept')->contains('lasik')
                && collect($rows)->pluck('dept')->contains('clinic')
                && collect($rows)->pluck('dept')->contains('surgery'))
        );
    }

    public function test_cases_report_shows_all_when_no_module_disabled(): void
    {
        $response = $this->actingAs($this->user)->get('/reports/cases?from='.today()->subDay()->toDateString().'&to='.today()->addDay()->toDateString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => collect($rows)->pluck('dept')->contains('lasik')
                && collect($rows)->pluck('dept')->contains('clinic')
                && collect($rows)->pluck('dept')->contains('surgery'))
        );
    }

    public function test_surgeries_report_hides_disabled_module_rows(): void
    {
        Surgery::create([
            'booking_id' => Booking::where('dept', 'surgery')->first()->id,
            'dept' => 'surgery',
            'status' => ScheduledState::$name,
            'scheduled_at' => today(),
        ]);
        Surgery::create([
            'booking_id' => Booking::where('dept', 'lasik')->first()->id,
            'dept' => 'lasik',
            'status' => ScheduledState::$name,
            'scheduled_at' => today(),
        ]);

        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/reports/surgeries?from='.today()->subDay()->toDateString().'&to='.today()->addDay()->toDateString());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => ! collect($rows)->pluck('dept')->contains('lasik')
                && collect($rows)->pluck('dept')->contains('surgery'))
        );
    }
}
