<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorEntitlementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    private Doctor $omar;

    private Doctor $sara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit', 'booking.delete'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'clinic', 'price' => 5000, 'ins_price' => 5000]);
        $other = Service::create(['name' => 'ليزر', 'dept' => 'clinic', 'price' => 1000, 'ins_price' => 1000]);

        $this->omar = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->omar->services()->attach([$this->service->id => ['fee' => 750], $other->id => ['fee' => 200]]);
        $this->sara = Doctor::create(['name' => 'د. سارة', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->sara->services()->attach($this->service->id, ['fee' => 900]);

        $this->otherService = $other;
        $this->company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);
    }

    private Service $otherService;

    private InsuranceCompany $company;

    private function createInsuranceBooking(): Booking
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->omar->id, 'price' => 5000, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        return Booking::latest('id')->first();
    }

    private function editBooking(Booking $booking, array $overrides): TestResponse
    {
        return $this->actingAs($this->user)->put("/booking/{$booking->id}", array_merge([
            'patient_name' => $booking->patient_name, 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->omar->id, 'price' => 5000, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ], $overrides))->assertRedirect();
    }

    public function test_changing_the_doctor_repoints_and_recalculates_the_entitlement(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, ['doctor_id' => $this->sara->id]);

        $this->assertSame(1, DoctorEntitlement::where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('doctor_entitlements', [
            'booking_id' => $booking->id, 'doctor_id' => $this->sara->id, 'amount' => 900,
        ]);
    }

    public function test_changing_the_service_recalculates_the_amount(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, ['service_id' => $this->otherService->id, 'service_name' => 'ليزر', 'price' => 1000]);

        $this->assertDatabaseHas('doctor_entitlements', ['booking_id' => $booking->id, 'amount' => 200]);
    }

    public function test_saving_twice_without_changes_keeps_one_entitlement(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, []);
        $this->editBooking($booking, []);

        $this->assertSame(1, DoctorEntitlement::where('booking_id', $booking->id)->count());
    }

    public function test_switching_away_from_insurance_voids_the_entitlement(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, ['pay_method' => 'cash']);

        $this->assertSame(EntitlementStatus::Void, DoctorEntitlement::where('booking_id', $booking->id)->first()->status);
    }

    public function test_switching_to_contract_creates_the_entitlement(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->omar->id, 'price' => 5000,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();
        $booking = Booking::latest('id')->first();
        $this->assertDatabaseCount('doctor_entitlements', 0);

        $this->editBooking($booking, ['pay_method' => 'contract']);

        $this->assertDatabaseHas('doctor_entitlements', [
            'booking_id' => $booking->id, 'source' => 'contract', 'status' => 'pending', 'amount' => 750,
        ]);
    }

    public function test_a_settled_entitlement_is_not_modified_on_edit(): void
    {
        $booking = $this->createInsuranceBooking();
        DoctorEntitlement::where('booking_id', $booking->id)->update([
            'status' => EntitlementStatus::Settled, 'amount' => 750,
        ]);

        $this->editBooking($booking, ['doctor_id' => $this->sara->id])
            ->assertSessionHas('warning');

        $row = DoctorEntitlement::where('booking_id', $booking->id)->first();
        $this->assertSame($this->omar->id, $row->doctor_id);
        $this->assertEquals(750.0, (float) $row->amount);
        $this->assertSame(EntitlementStatus::Settled, $row->status);
    }

    public function test_cancelling_a_booking_voids_the_pending_entitlement(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'cancelled', 'cancel_reason' => 'اعتذار المريض',
        ])->assertRedirect();

        $this->assertSame(EntitlementStatus::Void, DoctorEntitlement::where('booking_id', $booking->id)->first()->status);
    }

    public function test_deleting_a_booking_removes_its_entitlement(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->actingAs($this->user)->delete("/booking/{$booking->id}")->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }
}
