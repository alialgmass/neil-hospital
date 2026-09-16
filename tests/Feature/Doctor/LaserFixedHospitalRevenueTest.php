<?php

namespace Tests\Feature\Doctor;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

/**
 * Business rule: Laser's hospital revenue is a fixed amount per service
 * (services.center_type = 'fixed', services.center_val = the fixed amount).
 * The doctor gets whatever remains after the 50 EGP development fee and
 * that fixed amount — the inverse of every other department's split.
 */
class LaserFixedHospitalRevenueTest extends TestCase
{
    use RefreshDatabase;

    private function insertLaserService(float $centerVal, float $devFee = 50): string
    {
        $id = Str::ulid()->toString();

        DB::table('services')->insert([
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
        ]);

        return $id;
    }

    private function insertLaserBooking(Doctor $doctor, string $serviceId, float $paid): string
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

        // (800 - 50) - 600 = 150 — the full discount came out of the
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
        // center_type is 'pct', not 'fixed' → falls back to the doctor's own
        // 40% of the full paid amount.
        $this->assertEquals(400.0, $result['total_claims']);
    }
}
