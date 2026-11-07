<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
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

        Permission::firstOrCreate(['name' => 'doctors.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'doctors.write', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['doctors.view', 'doctors.write', 'booking.view']);

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

    public function test_doctors_screen_exposes_pentacam_in_the_shared_departments_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/doctors');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('departments', fn ($departments) => collect($departments)
                ->contains(fn ($d) => $d['value'] === 'pentacam' && $d['label'] === 'البنتكام')));
    }

    public function test_booking_screen_exposes_pentacam_in_the_shared_departments_prop(): void
    {
        $response = $this->actingAs($this->user)->get('/booking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('departments', fn ($departments) => collect($departments)
                ->pluck('value')->contains('pentacam')));
    }

    public function test_disabling_the_pentacam_module_removes_it_from_the_departments_prop(): void
    {
        Setting::setValue(SystemModule::Pentacam->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/doctors');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('departments', fn ($departments) => ! collect($departments)->pluck('value')->contains('pentacam')
                && collect($departments)->pluck('value')->contains('clinic')));
    }

    public function test_creating_a_doctor_persists_a_pentacam_department_and_fee_override(): void
    {
        $response = $this->actingAs($this->user)->post('/doctors', [
            'name' => 'د. بنتكام',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
            'dept_fees' => [
                'pentacam' => ['fee_type' => 'fixed', 'fee_value' => 150],
            ],
        ]);

        $response->assertRedirect();
        $doctor = Doctor::where('name', 'د. بنتكام')->firstOrFail();
        $this->assertEqualsCanonicalizing(['clinic', 'pentacam'], $doctor->departments);
        $this->assertSame('fixed', $doctor->dept_fees['pentacam']['fee_type']);
        $this->assertEquals(150, $doctor->dept_fees['pentacam']['fee_value']);
    }

    public function test_updating_a_doctor_can_remove_the_pentacam_scope(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. تعديل', 'fee_type' => 'percentage', 'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
        ]);

        $response = $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => 'د. تعديل',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['clinic'],
        ]);

        $response->assertRedirect();
        $this->assertEqualsCanonicalizing(['clinic'], $doctor->fresh()->departments);
    }

    public function test_creating_a_doctor_without_departments_leaves_them_unscoped(): void
    {
        $response = $this->actingAs($this->user)->post('/doctors', [
            'name' => 'د. بلا قسم',
            'fee_type' => 'percentage',
            'fee_value' => 40,
        ]);

        $response->assertRedirect();
        $this->assertNull(Doctor::where('name', 'د. بلا قسم')->firstOrFail()->departments);
    }

    public function test_updating_a_doctor_can_clear_the_pentacam_fee_override(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. رسوم', 'fee_type' => 'percentage', 'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
            'dept_fees' => ['pentacam' => ['fee_type' => 'fixed', 'fee_value' => 150]],
        ]);

        $response = $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => 'د. رسوم',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
            'dept_fees' => [],
        ]);

        $response->assertRedirect();
        $this->assertArrayNotHasKey('pentacam', $doctor->fresh()->dept_fees ?? []);
    }

    public function test_a_saved_pentacam_fee_override_is_kept_when_the_client_resends_it(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. حفظ', 'fee_type' => 'percentage', 'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
            'dept_fees' => ['pentacam' => ['fee_type' => 'fixed', 'fee_value' => 150]],
        ]);

        Setting::setValue(SystemModule::Pentacam->settingKey(), 'false', 'modules');

        // The doctors form carries hidden-department dept_fees through the save
        // untouched, so a disabled Pentacam module must not lose the override.
        $response = $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => 'د. حفظ',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['clinic', 'pentacam'],
            'dept_fees' => [
                'clinic' => ['fee_type' => 'percentage', 'fee_value' => 30],
                'pentacam' => ['fee_type' => 'fixed', 'fee_value' => 150],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame('fixed', $doctor->fresh()->dept_fees['pentacam']['fee_type']);
        $this->assertEquals(150, $doctor->fresh()->dept_fees['pentacam']['fee_value']);
    }

    public function test_pentacam_scope_survives_an_update_that_does_not_touch_departments_even_when_module_disabled(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. ثابت', 'fee_type' => 'percentage', 'fee_value' => 40,
            'departments' => ['pentacam'],
        ]);

        Setting::setValue(SystemModule::Pentacam->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->put("/doctors/{$doctor->id}", [
            'name' => 'د. ثابت المعدل',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'departments' => ['pentacam'],
        ]);

        $response->assertRedirect();
        $this->assertEqualsCanonicalizing(['pentacam'], $doctor->fresh()->departments);
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
