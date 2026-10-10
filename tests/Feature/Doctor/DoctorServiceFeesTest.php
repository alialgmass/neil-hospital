<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorServiceFeesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $cataract;

    private Service $checkup;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'doctors.write', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->cataract = Service::create(['name' => 'مياه بيضاء', 'dept' => 'surgery', 'price' => 5000]);
        $this->checkup = Service::create(['name' => 'كشف عام', 'dept' => 'clinic', 'price' => 300]);
    }

    public function test_a_doctor_is_created_with_per_service_fees(): void
    {
        $this->actingAs($this->user)->post('/doctors', [
            'name' => 'د. عمر الجارحي',
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'services' => [
                ['service_id' => $this->cataract->id, 'fee' => 750],
                ['service_id' => $this->checkup->id, 'fee' => 120],
            ],
        ])->assertRedirect();

        $doctor = Doctor::where('name', 'د. عمر الجارحي')->firstOrFail();

        $this->assertDatabaseHas('doctor_service', [
            'doctor_id' => $doctor->id,
            'service_id' => $this->cataract->id,
            'fee' => 750,
        ]);
        $this->assertSame(750.0, $doctor->feeForService($this->cataract));
        $this->assertSame(120.0, $doctor->feeForService($this->checkup->id));
    }

    public function test_fee_for_service_returns_null_when_not_configured(): void
    {
        $doctor = Doctor::create(['name' => 'د. بدون خدمات', 'fee_type' => 'fixed', 'fee_value' => 0]);

        $this->assertNull($doctor->feeForService($this->cataract));
    }

    public function test_updating_a_doctor_replaces_the_service_fee_set(): void
    {
        $doctor = Doctor::create(['name' => 'د. تحديث', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $doctor->services()->attach($this->cataract->id, ['fee' => 750]);

        $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => $doctor->name,
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'services' => [
                ['service_id' => $this->cataract->id, 'fee' => 900],
                ['service_id' => $this->checkup->id, 'fee' => 150],
            ],
        ])->assertRedirect();

        $this->assertSame(900.0, $doctor->fresh()->feeForService($this->cataract));
        $this->assertSame(2, $doctor->fresh()->services()->count());
    }

    public function test_removing_all_services_on_update_clears_the_pivot(): void
    {
        $doctor = Doctor::create(['name' => 'د. مسح', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $doctor->services()->attach($this->cataract->id, ['fee' => 750]);

        $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => $doctor->name,
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'services' => [],
        ])->assertRedirect();

        $this->assertSame(0, $doctor->fresh()->services()->count());
    }

    public function test_a_negative_service_fee_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/doctors', [
            'name' => 'د. خطأ',
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'services' => [
                ['service_id' => $this->cataract->id, 'fee' => -5],
            ],
        ])->assertSessionHasErrors('services.0.fee');

        $this->assertDatabaseMissing('doctors', ['name' => 'د. خطأ']);
    }
}
