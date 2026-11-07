<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorShift;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'doctors.write', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'doctors.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'doctors.delete', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['doctors.write', 'doctors.view', 'doctors.delete']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_deleting_a_doctor_with_no_related_records_succeeds(): void
    {
        $doctor = Doctor::create(['name' => 'د. للحذف', 'fee_type' => 'percentage', 'fee_value' => 40]);

        $response = $this->actingAs($this->user)->delete("/doctors/{$doctor->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
    }

    public function test_deleting_a_doctor_with_shifts_is_blocked(): void
    {
        $doctor = Doctor::create(['name' => 'د. له شِفت', 'fee_type' => 'percentage', 'fee_value' => 40]);
        DoctorShift::create([
            'doctor_id' => $doctor->id,
            'shift_date' => now()->toDateString(),
            'dept' => 'clinic',
        ]);

        $response = $this->actingAs($this->user)->delete("/doctors/{$doctor->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_guests_cannot_delete_doctors(): void
    {
        $doctor = Doctor::create(['name' => 'د. محمي', 'fee_type' => 'percentage', 'fee_value' => 40]);

        $response = $this->delete("/doctors/{$doctor->id}");

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_a_user_with_only_doctors_write_cannot_delete(): void
    {
        $role = Role::firstOrCreate(['name' => 'editor_only', 'guard_name' => 'web']);
        $role->givePermissionTo(['doctors.write', 'doctors.view']);
        $editor = User::factory()->create();
        $editor->assignRole($role);

        $doctor = Doctor::create(['name' => 'د. محمي بصلاحية', 'fee_type' => 'percentage', 'fee_value' => 40]);

        $response = $this->actingAs($editor)->delete("/doctors/{$doctor->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }
}
