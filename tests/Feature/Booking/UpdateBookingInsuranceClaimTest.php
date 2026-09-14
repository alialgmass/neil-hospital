<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Insurance\Models\InsuranceClaim;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpdateBookingInsuranceClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 500, 'ins_price' => 500,
        ]);
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-002',
            'patient_name' => 'أحمد سمير',
            'dept' => 'clinic',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'visit_date' => '2026-04-20',
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_assigning_an_insurance_company_on_edit_creates_a_claim(): void
    {
        $booking = $this->createBooking();
        $company = InsuranceCompany::create(['name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80]);

        $this->assertDatabaseMissing('insurance_claims', ['booking_id' => $booking->id]);

        $response = $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'ins_company_id' => $company->id,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 400,
            'paid_amount' => 100,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ]);

        $response->assertRedirect();

        $claim = InsuranceClaim::where('booking_id', $booking->id)->first();
        $this->assertNotNull($claim, 'Insurance claim should be created when a company is assigned on edit');
        $this->assertSame($company->id, $claim->insurance_company_id);
        $this->assertEquals(400.0, (float) $claim->insurance_share);
        $this->assertEquals(100.0, (float) $claim->patient_share);
    }

    public function test_editing_a_booking_that_already_has_a_claim_does_not_duplicate_it(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80]);
        $booking = $this->createBooking([
            'ins_company_id' => $company->id,
            'ins_amount' => 400,
            'pay_method' => 'insurance',
        ]);
        InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $company->id,
            'service_id' => $this->service->id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'service_name' => $this->service->name,
            'invoice_amount' => 500,
            'discount' => 0,
            'insurance_share' => 400,
            'patient_share' => 100,
            'approved_amount' => 0,
            'paid_amount' => 0,
            'status' => 'draft',
            'service_date' => '2026-04-20',
            'claim_date' => today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'ins_company_id' => $company->id,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 400,
            'paid_amount' => 100,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ])->assertRedirect();

        $this->assertSame(1, InsuranceClaim::where('booking_id', $booking->id)->count());
    }

    public function test_editing_a_booking_with_a_draft_claim_updates_it_when_the_company_changes(): void
    {
        $companyA = InsuranceCompany::create(['name' => 'شركة أ', 'coverage_pct' => 80]);
        $companyB = InsuranceCompany::create(['name' => 'شركة ب', 'coverage_pct' => 60]);
        $booking = $this->createBooking([
            'ins_company_id' => $companyA->id,
            'ins_amount' => 400,
            'pay_method' => 'insurance',
        ]);
        InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $companyA->id,
            'service_id' => $this->service->id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'service_name' => $this->service->name,
            'invoice_amount' => 500,
            'discount' => 0,
            'insurance_share' => 400,
            'patient_share' => 100,
            'approved_amount' => 0,
            'paid_amount' => 0,
            'status' => 'draft',
            'service_date' => '2026-04-20',
            'claim_date' => today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'ins_company_id' => $companyB->id,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 300,
            'paid_amount' => 200,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ])->assertRedirect();

        $this->assertSame(1, InsuranceClaim::where('booking_id', $booking->id)->count());
        $claim = InsuranceClaim::where('booking_id', $booking->id)->first();
        $this->assertSame($companyB->id, $claim->insurance_company_id);
        $this->assertEquals(300.0, (float) $claim->insurance_share);
        $this->assertEquals(200.0, (float) $claim->patient_share);
    }

    public function test_editing_a_booking_does_not_rewrite_a_claim_that_already_left_draft(): void
    {
        $companyA = InsuranceCompany::create(['name' => 'شركة أ', 'coverage_pct' => 80]);
        $companyB = InsuranceCompany::create(['name' => 'شركة ب', 'coverage_pct' => 60]);
        $booking = $this->createBooking([
            'ins_company_id' => $companyA->id,
            'ins_amount' => 400,
            'pay_method' => 'insurance',
        ]);
        InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $companyA->id,
            'service_id' => $this->service->id,
            'patient_name' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'service_name' => $this->service->name,
            'invoice_amount' => 500,
            'discount' => 0,
            'insurance_share' => 400,
            'patient_share' => 100,
            'approved_amount' => 0,
            'paid_amount' => 0,
            'status' => 'submitted',
            'service_date' => '2026-04-20',
            'claim_date' => today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'ins_company_id' => $companyB->id,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 300,
            'paid_amount' => 200,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ])->assertRedirect();

        $claim = InsuranceClaim::where('booking_id', $booking->id)->first();
        $this->assertSame($companyA->id, $claim->insurance_company_id, 'A submitted claim must not be silently rewritten.');
        $this->assertEquals(400.0, (float) $claim->insurance_share);
    }

    public function test_service_is_required_when_assigning_an_insurance_company_on_edit(): void
    {
        $booking = $this->createBooking();
        $company = InsuranceCompany::create(['name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80]);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'ins_company_id' => $company->id,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 400,
            'paid_amount' => 100,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'waiting',
        ])->assertSessionHasErrors('service_id');

        $this->assertDatabaseMissing('insurance_claims', ['booking_id' => $booking->id]);
    }
}
