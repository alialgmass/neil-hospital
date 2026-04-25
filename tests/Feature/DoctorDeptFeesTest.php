<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

class DoctorDeptFeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_stores_and_retrieves_dept_fees(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. تست',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'dept_fees' => [
                'clinic' => ['fee_type' => 'percentage', 'fee_value' => 30],
                'surgery' => ['fee_type' => 'fixed', 'fee_value' => 500],
            ],
        ]);

        $this->assertDatabaseHas('doctors', ['name' => 'د. تست']);

        $fresh = Doctor::find($doctor->id);
        $this->assertIsArray($fresh->dept_fees);
        $this->assertEquals('percentage', $fresh->dept_fees['clinic']['fee_type']);
        $this->assertEquals(30, $fresh->dept_fees['clinic']['fee_value']);
        $this->assertEquals('fixed', $fresh->dept_fees['surgery']['fee_type']);
        $this->assertEquals(500, $fresh->dept_fees['surgery']['fee_value']);
    }

    public function test_doctor_with_no_dept_fees_is_null(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. آخر',
            'fee_type' => 'percentage',
            'fee_value' => 50,
        ]);

        $this->assertNull($doctor->fresh()->dept_fees);
    }

    public function test_claims_service_uses_dept_fee_override_for_percentage(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. محمد',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'dept_fees' => [
                'laser' => ['fee_type' => 'percentage', 'fee_value' => 25],
            ],
        ]);

        // Simulate a laser booking
        $bookingId = Str::ulid()->toString();
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'doctor_id' => $doctor->id,
            'patient_name' => 'مريض تست',
            'file_no' => 'T001',
            'dept' => 'laser',
            'service_name' => 'ليزر علاجي',
            'price' => 1000,
            'paid_amount' => 1000,
            'ins_amount' => 0,
            'pay_status' => 'paid',
            'visit_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DoctorClaimsService::class);
        $result = $service->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // 25% of 1000 = 250 (uses dept_fee override, not global 40%)
        $this->assertEquals(250.0, $result['total_claims']);
    }

    public function test_claims_service_falls_back_to_global_fee_when_no_dept_override(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. سارة',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'dept_fees' => [
                'clinic' => ['fee_type' => 'percentage', 'fee_value' => 30],
            ],
        ]);

        $bookingId = Str::ulid()->toString();
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'doctor_id' => $doctor->id,
            'patient_name' => 'مريض تست 2',
            'file_no' => 'T002',
            'dept' => 'laser',
            'service_name' => 'ليزر',
            'price' => 1000,
            'paid_amount' => 1000,
            'ins_amount' => 0,
            'pay_status' => 'paid',
            'visit_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(DoctorClaimsService::class);
        $result = $service->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // No laser override → global 40% of 1000 = 400
        $this->assertEquals(400.0, $result['total_claims']);
    }
}
