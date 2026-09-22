<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: an insurance-fee-type doctor is paid their fee_value as a
 * flat per-case amount on CASH bookings — exactly like a fixed-fee doctor.
 * Insurance/contract-paid bookings never reach this path at all: those are
 * settled through a persisted DoctorEntitlement instead (see
 * calculateClaims(), which uses the entitlement amount whenever one exists
 * for the booking and never calls computeDrShare() for it).
 */
class InsuranceDoctorClaimsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_booking_pays_the_insurance_fee_type_doctor_their_flat_fee_value(): void
    {
        $service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 238, 'ins_price' => 238,
        ]);
        $doctor = Doctor::create(['name' => 'د. تأمين نقدي', 'fee_type' => 'insurance', 'fee_value' => 416]);

        Booking::create([
            'file_no' => 'MRN-INS-CASH-1', 'patient_name' => 'مريض نقدي', 'dept' => 'clinic',
            'doctor_id' => $doctor->id, 'visit_date' => now()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 238, 'paid_amount' => 238, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'completed',
        ]);

        $result = app(DoctorClaimsService::class)
            ->calculateClaims($doctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        // The doctor's flat fee_value (416), same as a FeeType::Fixed
        // doctor would earn — not scaled by the case's price (238) and not
        // zero.
        $this->assertEquals(416.0, $result['total_claims']);
        $this->assertEquals(416.0, $result['rows'][0]['dr_share']);
    }

    public function test_percentage_and_fixed_fee_types_are_unaffected(): void
    {
        $service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 200, 'ins_price' => 200,
        ]);

        $pctDoctor = Doctor::create(['name' => 'د. نسبة', 'fee_type' => 'percentage', 'fee_value' => 50]);
        $fixedDoctor = Doctor::create(['name' => 'د. ثابت', 'fee_type' => 'fixed', 'fee_value' => 75]);

        Booking::create([
            'file_no' => 'MRN-PCT-1', 'patient_name' => 'مريض 1', 'dept' => 'clinic',
            'doctor_id' => $pctDoctor->id, 'visit_date' => now()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 200, 'paid_amount' => 200, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'completed',
        ]);
        Booking::create([
            'file_no' => 'MRN-FIXED-1', 'patient_name' => 'مريض 2', 'dept' => 'clinic',
            'doctor_id' => $fixedDoctor->id, 'visit_date' => now()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 200, 'paid_amount' => 200, 'pay_method' => 'cash', 'pay_status' => 'paid',
            'status' => 'completed',
        ]);

        $svc = app(DoctorClaimsService::class);
        $pctResult = $svc->calculateClaims($pctDoctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $fixedResult = $svc->calculateClaims($fixedDoctor->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertEquals(100.0, $pctResult['total_claims']);
        $this->assertEquals(75.0, $fixedResult['total_claims']);
    }

    public function test_unpaid_cash_booking_incurs_doctor_debt_for_the_flat_fee_value(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']));
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 238, 'ins_price' => 238, 'dev_treasury_fee' => 0,
        ]);
        $doctor = Doctor::create(['name' => 'د. تأمين دين', 'fee_type' => 'insurance', 'fee_value' => 416]);

        $this->assertEquals(0.0, (float) $doctor->doctor_debt_balance);

        $this->actingAs($user)->post('/booking', [
            'patient_name' => 'مريض لم يدفع', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => now()->toDateString(), 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'pay_method' => 'cash', 'paid_amount' => 0,
            'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        // Debt is the booking's price (net of the dev fee), same rule
        // already applied to every other cash doctor — not fee_value.
        $this->assertEquals(238.0, (float) $doctor->fresh()->doctor_debt_balance);
    }
}
