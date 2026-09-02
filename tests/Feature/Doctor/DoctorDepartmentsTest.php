<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorDepartmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'doctors.write', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['doctors.write', 'booking.view']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_doctor_with_no_departments_works_in_every_department(): void
    {
        $doctor = Doctor::create(['name' => 'د. عام', 'fee_type' => 'percentage', 'fee_value' => 40]);

        $this->assertTrue($doctor->worksInDept('clinic'));
        $this->assertTrue($doctor->worksInDept('surgery'));
    }

    public function test_doctor_scoped_to_departments_only_works_in_those(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 40,
            'departments' => ['surgery', 'lasik'],
        ]);

        $this->assertTrue($doctor->worksInDept('surgery'));
        $this->assertTrue($doctor->worksInDept('lasik'));
        $this->assertFalse($doctor->worksInDept('clinic'));
    }

    public function test_creating_a_doctor_persists_departments(): void
    {
        $response = $this->actingAs($this->user)->post('/doctors', [
            'name' => 'د. عيون',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['clinic', 'labs'],
        ]);

        $response->assertRedirect();
        $doctor = Doctor::where('name', 'د. عيون')->firstOrFail();
        $this->assertEqualsCanonicalizing(['clinic', 'labs'], $doctor->departments);
    }

    public function test_booking_form_resources_expose_doctor_departments_for_client_side_filtering(): void
    {
        Doctor::create([
            'name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 40,
            'is_active' => true, 'departments' => ['surgery'],
        ]);
        Doctor::create(['name' => 'د. عام', 'fee_type' => 'percentage', 'fee_value' => 40, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('doctors', fn ($doctors) => collect($doctors)->firstWhere('name', 'د. جراح')['departments'] === ['surgery']
                && collect($doctors)->firstWhere('name', 'د. عام')['departments'] === null));
    }
}
