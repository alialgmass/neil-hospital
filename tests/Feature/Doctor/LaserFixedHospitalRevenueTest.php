<?php

namespace Tests\Feature\Doctor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

/**
 * Business rule: Laser's hospital cut comes before the doctor's share, via
 * one of two configurations:
 *
 * - Legacy fixed-center service (services.center_type = 'fixed',
 *   services.center_val = the fixed amount) — the cut applies regardless of
 *   eye side, and is the ONLY thing that applies when the booking has no
 *   recorded eye side.
 * - Eye-priced service, booking has an eye side recorded: the cut is the
 *   service's one_eye_price / both_eyes_price for that side — the patient
 *   is expected to pay above this floor, and the doctor keeps whatever they
 *   paid beyond it (0 if they paid exactly the floor, never negative).
 *
 * In both cases the doctor gets whatever remains after the 50 EGP
 * development fee and the hospital's cut — the inverse of every other
 * department's split.
 */
class LaserFixedHospitalRevenueTest extends TestCase
{
    use RefreshDatabase;

    private function insertLaserService(float $centerVal, float $devFee = 50, array $overrides = []): string
    {
        $id = Str::ulid()->toString();

        DB::table('services')->insert(array_merge([
            'id' => $id,
            'name' => 'جلسة ليزر',
            'dept' => 'laser',
            'price' => 1000,
            'center_type' => 'fixed',
            'center_val' => $centerVal,
            'center_share' => $centerVal,
            'dr_share' => 0,
            'dev_treasury_fee' => $devFee,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function insertLaserBooking(Doctor $doctor, string $serviceId, float $paid, ?string $eyeSide = null): string
    {
        $id = Str::ulid()->toString();

        DB::table('bookings')->insert([
            'id' => $id,
            'doctor_id' => $doctor->id,
            'patient_name' => 'مريض ليزر',
            'file_no' => 'T-'.substr($id, -6),
            'dept' => 'laser',
            'service_id' => $serviceId,
            'service_name' => 'جلسة ليزر',
            'pay_method' => 'cash',
            'price' => $paid,
            'paid_amount' => $paid,
            'ins_amount' => 0,
            'pay_status' => 'paid',
            'eye_side' => $eyeSide,
            'visit_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_doctor_fee_is_remainder_after_fixed_hospital_revenue_and_dev_fee(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 600);
        $this->insertLaserBooking($doctor, $service, paid: 1000);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // (1000 - 50 dev fee) - 600 fixed hospital revenue = 350.
        // NOT the doctor's own 40% (which would be 380).
        $this->assertEquals(350.0, $result['total_claims']);
    }

    public function test_patient_discount_reduces_doctor_fee_not_fixed_hospital_revenue(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر خصم', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 600);

        // Patient paid 800 instead of the 1000 list price (a 200 discount).
        $this->insertLaserBooking($doctor, $service, paid: 800);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // (800 - 50) - 600 = 150 -- the full discount came out of the
        // doctor's share; the fixed 600 hospital revenue is untouched.
        $this->assertEquals(150.0, $result['total_claims']);
    }

    public function test_laser_service_without_fixed_revenue_configured_falls_back_to_doctor_fee_type(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر نسبة', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $id = Str::ulid()->toString();

        DB::table('services')->insert([
            'id' => $id,
            'name' => 'جلسة ليزر أخرى',
            'dept' => 'laser',
            'price' => 1000,
            'center_type' => 'pct',
            'center_val' => 40,
            'center_share' => 400,
            'dr_share' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertLaserBooking($doctor, $id, paid: 1000);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // No dev_treasury_fee configured on this service (defaults to 0) and
        // center_type is 'pct', not 'fixed', and the booking has no eye side
        // -> falls back to the doctor's own 40% of the full paid amount.
        $this->assertEquals(400.0, $result['total_claims']);
    }

    /**
     * Regression guard: a migration backfilled one_eye_price = price on
     * EVERY pre-existing service, so a legacy fixed-center booking with no
     * recorded eye side must never match the eye-price branch -- it would
     * silently zero the doctor's share (paid - one_eye_price ~ 0).
     */
    public function test_legacy_fixed_center_booking_without_eye_side_ignores_backfilled_eye_prices(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر قديم', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 600, overrides: [
            // Simulates the backfill: one_eye_price/both_eyes_price equal
            // to price on a service that predates eye tracking.
            'one_eye_price' => 1000,
            'both_eyes_price' => 2000,
        ]);
        $this->insertLaserBooking($doctor, $service, paid: 1000, eyeSide: null);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // Must still use center_val (600), not one_eye_price (1000).
        // (1000 - 50 dev fee) - 600 = 350, not (1000 - 50) - 1000 = 0.
        $this->assertEquals(350.0, $result['total_claims']);
    }

    public function test_one_eye_booking_deducts_the_one_eye_price_from_the_doctor_share(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر عين واحدة', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 0, overrides: [
            'center_type' => 'pct',
            'one_eye_price' => 600,
            'both_eyes_price' => 1000,
        ]);
        $this->insertLaserBooking($doctor, $service, paid: 900, eyeSide: 'OD');

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // (900 - 50 dev fee) - 600 one-eye price = 250 -- the markup above
        // the one-eye floor.
        $this->assertEquals(250.0, $result['total_claims']);
    }

    public function test_both_eyes_booking_deducts_the_both_eyes_price_from_the_doctor_share(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر عينين', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 0, overrides: [
            'center_type' => 'pct',
            'one_eye_price' => 600,
            'both_eyes_price' => 1000,
        ]);
        $this->insertLaserBooking($doctor, $service, paid: 1400, eyeSide: 'OU');

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // (1400 - 50 dev fee) - 1000 both-eyes price = 350.
        $this->assertEquals(350.0, $result['total_claims']);
    }

    public function test_eye_priced_booking_paid_exactly_the_floor_yields_zero_doctor_share(): void
    {
        $doctor = Doctor::create(['name' => 'د. ليزر بدون هامش', 'fee_type' => 'percentage', 'fee_value' => 40]);
        $service = $this->insertLaserService(centerVal: 0, devFee: 0, overrides: [
            'center_type' => 'pct',
            'one_eye_price' => 4455,
            'both_eyes_price' => 5656,
        ]);
        $this->insertLaserBooking($doctor, $service, paid: 4455, eyeSide: 'OD');

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // Paid exactly the one-eye floor with no dev fee -- no markup, so no
        // doctor share. Not negative, and not the doctor's own 40%.
        $this->assertEquals(0.0, $result['total_claims']);
    }
}
