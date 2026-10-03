<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Enums\PayStatus;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\DelegationStatus;
use Modules\Doctor\Models\BookingDoctorDelegation;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Lasik follows the laser rule exactly: the doctor's share is the paid amount
 * minus the service's own price for the booked side (and the dev-treasury fee,
 * netted upstream), never a percentage of it and never reduced by supplies.
 *
 * The point of this test is that the claims report (computeDrShare) and the
 * payment path (computeShareForPayment) now agree for lasik — they disagreed
 * before, the report deducting supplies while the payment deducted the
 * service price.
 */
class DoctorLasikShareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $lasik;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->lasik = Service::create([
            'name' => 'ليزك سادس', 'dept' => 'lasik', 'price' => 8000,
            'dev_treasury_fee' => 200,
            'one_eye_price' => 4000, 'both_eyes_price' => 7000,
            'center_type' => 'pct', 'center_val' => 50, 'center_share' => 4000, 'dr_share' => 4000,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'د. ليزك', 'fee_type' => 'percentage', 'fee_value' => 40, 'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'file_no' => 'MRN-LASIK-1',
            'patient_name' => 'مريض ليزك',
            'dept' => 'lasik',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-05-10',
            'service_id' => $this->lasik->id,
            'service_name' => $this->lasik->name,
            'eye_side' => 'OD',
            'price' => 6000,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 6000,
            'pay_method' => PayMethod::Cash,
            'pay_status' => PayStatus::Paid,
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Report-path input: computeDrShare() reads plain rows off the query
     * builder, so hand it the stdClass shape with an overridden pay_method.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function reportRow(Booking $booking, array $overrides = []): object
    {
        $row = (object) $booking->fresh()->toArray();

        foreach ($overrides as $key => $value) {
            $row->{$key} = $value;
        }

        return $row;
    }

    private function claims(): DoctorClaimsService
    {
        return app(DoctorClaimsService::class);
    }

    public function test_lasik_report_and_payment_paths_compute_the_same_share(): void
    {
        $service = app(DoctorClaimsService::class);

        // OD → one_eye_price 4000. Paid 6000 less dev fee 200 = 5800, minus
        // the 4000 service price leaves 1800 for the doctor.
        $fromPayment = $service->computeShareForPayment($this->doctor, $this->booking, 6000.0, true);
        $fromReport = $service->computeDrShare($this->doctor, $this->reportRow($this->booking, ['pay_method' => 'cash']));

        $this->assertEquals(1800.0, $fromPayment);
        $this->assertEquals($fromPayment, $fromReport);
    }

    public function test_lasik_share_is_not_reduced_by_surgery_supplies(): void
    {
        // A lasik case can carry a surgery row (scheduling creates one). Its
        // supplies must not be deducted the way they are for surgery.
        Surgery::create([
            'booking_id' => $this->booking->id,
            'dept' => 'lasik',
            'supply_total' => 2500,
        ]);

        $share = $this->claims()
            ->computeDrShare($this->doctor, $this->reportRow($this->booking, ['pay_method' => 'cash']));

        $this->assertEquals(1800.0, $share);
    }

    public function test_lasik_uses_both_eyes_price_for_an_ou_booking(): void
    {
        $this->booking->update(['eye_side' => 'OU']);

        $share = $this->claims()
            ->computeDrShare($this->doctor, $this->reportRow($this->booking, ['pay_method' => 'cash']));

        // OU → both_eyes_price 7000. 5800 − 7000 floors at 0.
        $this->assertEquals(0.0, $share);
    }

    public function test_lasik_insurance_uses_the_doctors_fixed_fee_not_the_center_split(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة', 'coverage_pct' => 80]);
        $this->booking->update([
            'pay_method' => PayMethod::Insurance,
            'ins_amount' => 5000,
        ]);

        $service = app(DoctorClaimsService::class);
        $this->doctor->services()->attach($this->lasik->id, ['fee' => 1200]);

        $fromPayment = $service->computeShareForPayment($this->doctor, $this->booking, 0.0, true);
        $fromReport = $service->computeDrShare($this->doctor, $this->reportRow($this->booking, ['pay_method' => 'insurance']));

        $this->assertEquals(1200.0, $fromPayment);
        $this->assertEquals($fromPayment, $fromReport);
        $this->assertNotNull($company);
    }

    public function test_lasik_claims_row_reports_the_service_price_split(): void
    {
        $row = app(DoctorClaimsService::class)
            ->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'][0];

        $this->assertEquals(1800.0, $row['dr_share']);
        $this->assertEquals(0.0, $row['debt_incurred']);
    }

    public function test_lasik_claims_use_the_collected_amount_when_the_price_is_the_service_price(): void
    {
        // Booking price = the service's own one-eye price; the patient paid
        // 6000 on top of it at the pay screen. Doctor keeps 6000 − 4000 − 200.
        $this->booking->update(['price' => 4000, 'paid_amount' => 6000]);

        $row = $this->claims()->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'][0];

        $this->assertEquals(1800.0, $row['dr_share']);
    }

    public function test_laser_claims_use_the_collected_amount_when_the_price_is_the_service_price(): void
    {
        $laser = Service::create([
            'name' => 'ليزر', 'dept' => 'laser', 'price' => 1000,
            'dev_treasury_fee' => 50, 'one_eye_price' => 1000, 'both_eyes_price' => 1800,
            'center_type' => 'pct', 'center_val' => 50, 'center_share' => 500, 'dr_share' => 500,
        ]);
        $this->booking->update([
            'dept' => 'laser', 'service_id' => $laser->id, 'eye_side' => 'OU',
            'price' => 1800, 'paid_amount' => 2500,
        ]);

        $row = $this->claims()->calculateClaims($this->doctor->id, '2026-01-01', '2026-12-31')['rows'][0];

        // 2500 − 1800 − 50
        $this->assertEquals(650.0, $row['dr_share']);
    }

    public function test_lasik_delegation_is_still_deducted_from_the_primary_doctor(): void
    {
        $delegate = Doctor::create(['name' => 'د. مساعد ليزك', 'fee_type' => 'fixed', 'fee_value' => 0]);
        BookingDoctorDelegation::create([
            'booking_id' => $this->booking->id,
            'doctor_id' => $delegate->id,
            'role' => 'delegate',
            'service_name' => 'مساعدة',
            'amount' => 300,
            'status' => DelegationStatus::Pending->value,
        ]);

        $share = app(DoctorClaimsService::class)
            ->computeShareForPayment($this->doctor, $this->booking, 6000.0, true);

        $this->assertEquals(1500.0, $share);
    }

    public function test_lasik_installments_only_deduct_the_service_price_once(): void
    {
        $service = app(DoctorClaimsService::class);

        $first = $service->computeShareForPayment($this->doctor, $this->booking, 3000.0, true);
        // Second installment: no dev fee, no service price — the price was
        // already deducted on the first payment.
        $second = $service->computeShareForPayment($this->doctor, $this->booking, 3000.0, false);

        $this->assertEquals(0.0, $first);   // 2800 − 4000 floors at 0
        $this->assertEquals(3000.0, $second);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
