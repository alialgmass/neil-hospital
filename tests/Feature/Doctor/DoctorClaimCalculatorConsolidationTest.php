<?php

namespace Tests\Feature\Doctor;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\ClaimCalculator;
use Modules\Doctor\Services\DoctorClaimsService;
use Tests\TestCase;

/**
 * Business rule: ClaimCalculator (doctors-list dashboard) and DoctorClaimsService
 * (claims report) must never disagree on the same doctor/period — ClaimCalculator
 * delegates all fee arithmetic to DoctorClaimsService instead of duplicating it.
 */
class DoctorClaimCalculatorConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function insertBooking(array $overrides): string
    {
        $id = Str::ulid()->toString();

        DB::table('bookings')->insert(array_merge([
            'id' => $id,
            'patient_name' => 'مريض تست',
            'file_no' => 'T-'.substr($id, -6),
            'price' => 0,
            'paid_amount' => 0,
            'ins_amount' => 0,
            'pay_status' => 'paid',
            'visit_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    public function test_claim_calculator_matches_claims_service_across_dept_fee_override_dev_fee_and_supplies(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. موحّد',
            'fee_type' => 'percentage',
            'fee_value' => 40,
            'dept_fees' => [
                'labs' => ['fee_type' => 'percentage', 'fee_value' => 27],
            ],
        ]);

        // Labs booking under a dept_fees override: 27% of 900 (development fee
        // is only netted at payment time via computeShareForPayment/booking
        // controller flow, so the raw price here is the calculation base).
        $this->insertBooking([
            'doctor_id' => $doctor->id,
            'dept' => 'labs',
            'service_name' => 'فحص نظر',
            'price' => 900,
            'paid_amount' => 900,
        ]);

        // Surgery booking: paid − supply_total.
        $surgeryBookingId = $this->insertBooking([
            'doctor_id' => $doctor->id,
            'dept' => 'surgery',
            'service_name' => 'عملية مياه بيضاء',
            'pay_method' => 'cash',
            'price' => 10000,
            'paid_amount' => 10000,
        ]);

        DB::table('surgeries')->insert([
            'id' => Str::ulid()->toString(),
            'booking_id' => $surgeryBookingId,
            'dept' => 'surgery',
            'status' => 'completed',
            'supply_total' => 2360,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $serviceResult = app(DoctorClaimsService::class)->calculateClaims($doctor->id, $from, $to);
        $calculatorResult = app(ClaimCalculator::class)->calculate($doctor->fresh(), Carbon::parse($from), Carbon::parse($to));

        // 27% of 900 = 243; (10000 - 2360) = 7640; total = 7883.
        $this->assertEquals(7883.0, $serviceResult['total_claims']);
        $this->assertEquals($serviceResult['total_claims'], $calculatorResult['stats']['total_claim']);
        $this->assertSame(count($serviceResult['rows']), $calculatorResult['stats']['booking_count']);
    }

    public function test_pentacam_booking_yields_zero_doctor_fee_in_both_calculators(): void
    {
        $doctor = Doctor::create([
            'name' => 'د. بنتاكام',
            'fee_type' => 'percentage',
            'fee_value' => 40,
        ]);

        $this->insertBooking([
            'doctor_id' => $doctor->id,
            'dept' => 'pentacam',
            'service_name' => 'فحص بنتاكام',
            'price' => 500,
            'paid_amount' => 500,
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $serviceResult = app(DoctorClaimsService::class)->calculateClaims($doctor->id, $from, $to);
        $calculatorResult = app(ClaimCalculator::class)->calculate($doctor->fresh(), Carbon::parse($from), Carbon::parse($to));

        $this->assertEquals(0.0, $serviceResult['total_claims']);
        $this->assertEquals(0.0, $calculatorResult['stats']['total_claim']);
        $this->assertEquals(0.0, $serviceResult['rows'][0]['dr_share']);
    }
}
