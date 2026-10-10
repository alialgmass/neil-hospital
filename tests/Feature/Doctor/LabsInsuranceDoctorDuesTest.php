<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Enums\EntitlementStatus;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Insurance\Actions\UpdateInsuranceClaimAction;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Reporting\Services\ReportingService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LabsInsuranceDoctorDuesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $service;

    private InsuranceCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit', 'reports.financial'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'تحليل', 'dept' => 'labs', 'price' => 500, 'ins_price' => 500]);
        $this->doctor = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->doctor->services()->attach($this->service->id, ['fee' => 120]);
        $this->company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 100]);
    }

    private function createLabsInsuranceBooking(): Booking
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 500, 'ins_amount' => 500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        return Booking::latest('id')->firstOrFail();
    }

    private function settleClaim(Booking $booking): void
    {
        $claim = InsuranceClaim::where('booking_id', $booking->id)->firstOrFail();
        $action = app(UpdateInsuranceClaimAction::class);

        foreach (['submitted', 'approved', 'paid'] as $status) {
            $action->execute($claim->refresh(), ['status' => $status]);
        }
    }

    public function test_labs_insurance_booking_creates_no_doctor_due_until_claim_is_settled(): void
    {
        $booking = $this->createLabsInsuranceBooking();

        $this->assertDatabaseMissing('doctor_entitlements', ['booking_id' => $booking->id, 'status' => EntitlementStatus::Pending->value]);
        $this->assertSame(0.0, app(DoctorClaimsService::class)->computeDrShare($this->doctor, $booking));
    }

    public function test_doctor_due_is_recognised_once_the_claim_is_settled(): void
    {
        $booking = $this->createLabsInsuranceBooking();

        $this->settleClaim($booking);

        $this->assertDatabaseHas('doctor_entitlements', ['booking_id' => $booking->id, 'amount' => 120]);
        $this->assertSame(120.0, (float) DoctorEntitlement::where('booking_id', $booking->id)->value('amount'));
    }

    public function test_clinic_insurance_booking_is_not_deferred(): void
    {
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 500, 'ins_price' => 500]);
        $this->doctor->services()->attach($service->id, ['fee' => 75]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $this->doctor->id, 'price' => 500, 'ins_amount' => 500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $this->assertDatabaseHas('doctor_entitlements', ['booking_id' => Booking::latest('id')->value('id'), 'amount' => 75]);
    }

    public function test_labs_insurance_dues_export_lists_pending_and_settled(): void
    {
        $booking = $this->createLabsInsuranceBooking();

        $pending = app(ReportingService::class)->labsInsuranceDoctorDues('2026-05-01', '2026-05-31');
        $this->assertCount(1, $pending['rows']);
        $this->assertFalse($pending['rows'][0]->is_recognised);
        $this->assertSame(120.0, $pending['rows'][0]->doctor_due);

        $this->settleClaim($booking);

        $settled = app(ReportingService::class)->labsInsuranceDoctorDues('2026-05-01', '2026-05-31');
        $this->assertTrue($settled['rows'][0]->is_recognised);

        $this->actingAs($this->user)
            ->get('/reports/doctor-claims/labs-insurance/export?from=2026-05-01&to=2026-05-31')
            ->assertOk();
    }
}
