<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiServiceLabsBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $octService;

    private Service $angioService;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->octService = Service::create([
            'name' => 'OCT', 'dept' => 'labs', 'price' => 300, 'one_eye_price' => 300, 'ins_price' => 300,
        ]);
        $this->angioService = Service::create([
            'name' => 'أنجيوغرافيا', 'dept' => 'labs', 'price' => 500, 'one_eye_price' => 500, 'ins_price' => 500,
        ]);
    }

    public function test_a_labs_booking_can_be_created_with_several_services(): void
    {
        $response = $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض الفحوصات',
            'dept' => 'labs',
            'eye_side' => 'OD',
            'visit_date' => '2026-05-01',
            'service_ids' => [$this->octService->id, $this->angioService->id],
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ]);

        $response->assertRedirect();

        $booking = Booking::where('patient_name', 'مريض الفحوصات')->firstOrFail();

        $this->assertEquals(800.0, (float) $booking->price);
        $this->assertCount(2, $booking->services);
        $this->assertEqualsCanonicalizing(
            [$this->octService->id, $this->angioService->id],
            $booking->services->pluck('service_id')->all(),
        );
    }

    public function test_a_single_service_department_is_unaffected(): void
    {
        $clinicService = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 200, 'ins_price' => 200,
        ]);

        $response = $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض العيادة',
            'dept' => 'clinic',
            'eye_side' => 'OD',
            'visit_date' => '2026-05-01',
            'service_id' => $clinicService->id,
            'service_name' => $clinicService->name,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ]);

        $response->assertRedirect();

        $booking = Booking::where('patient_name', 'مريض العيادة')->firstOrFail();

        $this->assertEquals(200.0, (float) $booking->price);
        $this->assertCount(0, $booking->services);
    }

    public function test_updating_a_labs_booking_replaces_its_service_lines(): void
    {
        $booking = Booking::create([
            'file_no' => 'MRN-LABS-EDIT',
            'patient_name' => 'مريض تعديل',
            'dept' => 'labs',
            'visit_date' => '2026-05-01',
            'price' => 300,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'eye_side' => 'OD',
            'created_by' => $this->user->id,
        ]);
        $booking->services()->create([
            'service_id' => $this->octService->id,
            'service_name' => $this->octService->name,
            'price' => 300,
        ]);

        $response = $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => 'مريض تعديل',
            'dept' => 'labs',
            'eye_side' => 'OD',
            'visit_date' => '2026-05-01',
            'service_ids' => [$this->angioService->id],
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ]);

        $response->assertRedirect();

        $booking->refresh();
        $this->assertEquals(500.0, (float) $booking->price);
        $this->assertCount(1, $booking->services);
        $this->assertEquals($this->angioService->id, $booking->services->first()->service_id);
    }
}
