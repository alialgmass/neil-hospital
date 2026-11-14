<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: for Clinic and Labs (same as every other department), the
 * booking's stored price is derived server-side from the service's
 * one_eye_price/both_eyes_price for the booked eye side (or the service's
 * flat price when neither is configured) — see ServicePricingService.
 * The doctor's dues are then computed as a percentage/fixed amount of that
 * already eye-aware price, never off the service's raw list price.
 */
class ClinicLabsEyePriceDoctorShareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_clinic_booking_price_and_doctor_share_use_the_one_eye_price(): void
    {
        $service = Service::create([
            'name' => 'كشف عيون', 'dept' => 'clinic', 'price' => 200,
            'one_eye_price' => 200, 'both_eyes_price' => 350, 'ins_price' => 200,
        ]);
        $doctor = Doctor::create(['name' => 'د. عيادة', 'fee_type' => 'percentage', 'fee_value' => 50]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض عين واحدة', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => now()->toDateString(), 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 200,
            'pay_status' => 'paid', 'status' => 'completed',
        ])->assertRedirect();

        $booking = Booking::where('patient_name', 'مريض عين واحدة')->firstOrFail();
        $this->assertEquals(200.0, (float) $booking->price);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertEquals(100.0, $result['total_claims']);
    }

    public function test_clinic_booking_price_and_doctor_share_use_the_both_eyes_price(): void
    {
        $service = Service::create([
            'name' => 'كشف عيون', 'dept' => 'clinic', 'price' => 200,
            'one_eye_price' => 200, 'both_eyes_price' => 350, 'ins_price' => 200,
        ]);
        $doctor = Doctor::create(['name' => 'د. عيادة عينين', 'fee_type' => 'percentage', 'fee_value' => 50]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض عينين', 'dept' => 'clinic', 'eye_side' => 'OU',
            'visit_date' => now()->toDateString(), 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 350,
            'pay_status' => 'paid', 'status' => 'completed',
        ])->assertRedirect();

        $booking = Booking::where('patient_name', 'مريض عينين')->firstOrFail();
        $this->assertEquals(350.0, (float) $booking->price);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertEquals(175.0, $result['total_claims']);
    }

    public function test_labs_booking_price_and_doctor_share_use_the_eye_price(): void
    {
        $service = Service::create([
            'name' => 'فحص OCT', 'dept' => 'labs', 'price' => 300,
            'one_eye_price' => 300, 'both_eyes_price' => 500, 'ins_price' => 300,
        ]);
        $doctor = Doctor::create(['name' => 'د. فحوصات', 'fee_type' => 'fixed', 'fee_value' => 100]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض فحص عينين', 'dept' => 'labs', 'eye_side' => 'OU',
            'visit_date' => now()->toDateString(), 'service_ids' => [$service->id],
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 500,
            'pay_status' => 'paid', 'status' => 'completed',
        ])->assertRedirect();

        $booking = Booking::where('patient_name', 'مريض فحص عينين')->firstOrFail();
        $this->assertEquals(500.0, (float) $booking->price);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // Fixed fee_type: the doctor's own flat rate, unaffected by price —
        // this proves eye pricing flows through price/booking regardless of
        // which fee_type the doctor uses on top of it.
        $this->assertEquals(100.0, $result['total_claims']);
    }

    public function test_service_without_eye_prices_falls_back_to_flat_price_doubled_for_both_eyes(): void
    {
        $service = Service::create(['name' => 'خدمة بلا سعر عين', 'dept' => 'clinic', 'price' => 150, 'ins_price' => 150]);
        $doctor = Doctor::create(['name' => 'د. سعر ثابت', 'fee_type' => 'percentage', 'fee_value' => 20]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض عين واحدة سعر ثابت', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => now()->toDateString(), 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 150,
            'pay_status' => 'paid', 'status' => 'completed',
        ])->assertRedirect();

        // No one_eye_price/both_eyes_price configured -> the flat price is
        // used as-is for one eye ...
        $oneEyeBooking = Booking::where('patient_name', 'مريض عين واحدة سعر ثابت')->firstOrFail();
        $this->assertEquals(150.0, (float) $oneEyeBooking->price);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض عينين سعر ثابت', 'dept' => 'clinic', 'eye_side' => 'OU',
            'visit_date' => now()->toDateString(), 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 300,
            'pay_status' => 'paid', 'status' => 'completed',
        ])->assertRedirect();

        // ... and doubled for both eyes, since no explicit both_eyes_price
        // overrides that default.
        $bothEyesBooking = Booking::where('patient_name', 'مريض عينين سعر ثابت')->firstOrFail();
        $this->assertEquals(300.0, (float) $bothEyesBooking->price);
    }
}
