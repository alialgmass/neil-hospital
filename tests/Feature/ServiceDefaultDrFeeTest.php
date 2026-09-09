<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceDefaultDrFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'services.write', 'guard_name' => 'web']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.services', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_a_service_stores_a_default_doctor_fee(): void
    {
        $this->actingAs($this->user)->post('/services', [
            'name' => 'مياه بيضاء',
            'dept' => 'surgery',
            'price' => 5000,
            'center_type' => 'fixed',
            'center_val' => 500,
            'default_dr_fee' => 750,
        ])->assertRedirect();

        $service = Service::where('name', 'مياه بيضاء')->firstOrFail();
        $this->assertEquals(750.0, (float) $service->default_dr_fee);
    }

    public function test_compute_shares_does_not_overwrite_the_default_doctor_fee(): void
    {
        $this->actingAs($this->user)->post('/services', [
            'name' => 'ليزك',
            'dept' => 'lasik',
            'price' => 8000,
            'center_type' => 'pct',
            'center_val' => 30,
            'default_dr_fee' => 1200,
        ])->assertRedirect();

        $service = Service::where('name', 'ليزك')->firstOrFail();
        $this->assertEquals(1200.0, (float) $service->default_dr_fee);
        // dr_share is still the derived price-portion, untouched by the new field
        $this->assertEquals(5600.0, (float) $service->dr_share);
    }

    public function test_a_negative_default_doctor_fee_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/services', [
            'name' => 'خطأ',
            'dept' => 'clinic',
            'price' => 100,
            'center_type' => 'fixed',
            'center_val' => 0,
            'default_dr_fee' => -1,
        ])->assertSessionHasErrors('default_dr_fee');
    }
}
