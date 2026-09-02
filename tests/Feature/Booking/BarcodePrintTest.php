<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Booking\Models\Booking;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BarcodePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_page_exposes_patient_name_and_file_no(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $booking = Booking::create([
            'file_no' => 'MRN-BARCODE-1',
            'patient_name' => 'سارة أحمد',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/booking/{$booking->id}/barcode");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('booking/Barcode')
            ->where('booking.file_no', 'MRN-BARCODE-1')
            ->where('booking.patient_name', 'سارة أحمد'));
    }

    public function test_barcode_page_uses_the_configured_hospital_name(): void
    {
        Setting::setValue('hospital_name', 'مستشفى الأمل التخصصي');

        $permission = Permission::firstOrCreate(['name' => 'booking.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $booking = Booking::create([
            'file_no' => 'MRN-BARCODE-2',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/booking/{$booking->id}/barcode");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('settings.hospital_name', 'مستشفى الأمل التخصصي'));
    }
}
