<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmptyBundleDoctorClaimsTest extends TestCase
{
    use RefreshDatabase;

    public function test_priced_bundle_without_items_shows_in_doctor_claims(): void
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::create(['name' => 'r', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'surgery.write', 'guard_name' => 'web']));
        $user = User::factory()->create();
        $user->assignRole($role);

        $doctor = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $booking = Booking::create([
            'file_no' => 'MRN-1', 'patient_name' => 'مريض', 'dept' => 'surgery',
            'visit_date' => now()->toDateString(), 'price' => 6500, 'paid_amount' => 6500, 'doctor_id' => $doctor->id,
            'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'completed',
        ]);
        $surgery = Surgery::create([
            'booking_id' => $booking->id, 'surgeon_id' => $doctor->id, 'dept' => 'surgery', 'status' => 'completed',
            'supplies_used' => [], 'supply_total' => 0,
        ]);
        $bundle = SupplyBundle::create(['name' => 'بند فاضي', 'code' => 'B-1', 'price' => 300, 'is_active' => true]);

        $this->actingAs($user)
            ->post("/surgery/{$surgery->id}/supplies", [
                'bundles' => [['bundle_id' => $bundle->id, 'qty' => 1, 'selected_items' => []]],
            ])->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertEquals(300.0, (float) $surgery->supply_total);

        $claims = app(DoctorClaimsService::class)->calculateClaims($doctor->id, now()->toDateString(), now()->toDateString());
        $row = collect($claims['rows'])->firstWhere('booking_id', $booking->id);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['supplies']);
        $this->assertEquals(300.0, $row['supply_total']);
    }
}
