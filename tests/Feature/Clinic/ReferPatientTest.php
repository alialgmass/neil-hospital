<?php

namespace Tests\Feature\Clinic;

use App\Enums\EyeSide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReferPatientTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'clinic.view', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'clinic.write', 'guard_name' => 'web']),
        ]);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function makeBooking(array $attrs = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'P-100-123',
            'patient_name' => 'مريض تجريبي',
            'patient_phone' => '01000000000',
            'national_id' => '29012345678901',
            'dept' => 'clinic',
            'visit_date' => today()->toDateString(),
            'price' => 100, 'discount' => 0, 'ins_amount' => 0, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'confirmed',
            'created_by' => $this->user->id,
        ], $attrs));
    }

    public function test_referring_sets_referral_to_on_the_clinic_sheet(): void
    {
        $booking = $this->makeBooking();
        ClinicSheet::create(['booking_id' => $booking->id, 'diagnosis' => 'إعتام عدسة', 'recorded_at' => now()]);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'surgery'])
            ->assertRedirect();

        $this->assertDatabaseHas((new ClinicSheet)->getTable(), [
            'booking_id' => $booking->id,
            'referral_to' => 'surgery',
        ]);
    }

    public function test_referring_creates_a_clinic_sheet_when_none_exists_yet(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertRedirect();

        $this->assertDatabaseHas((new ClinicSheet)->getTable(), [
            'booking_id' => $booking->id,
            'referral_to' => 'labs',
        ]);
    }

    public function test_create_follow_up_creates_a_same_day_booking_in_the_target_dept(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs', 'create_follow_up' => true])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'labs')->where('id', '!=', $booking->id)->sole();
        $this->assertSame('مريض تجريبي', $followUp->patient_name);
        $this->assertSame(today()->toDateString(), $followUp->visit_date->toDateString());
    }

    public function test_follow_up_booking_references_the_original_visit_in_its_note(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'surgery', 'create_follow_up' => true])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'surgery')->where('id', '!=', $booking->id)->sole();
        $this->assertStringContainsString($booking->file_no, $followUp->visit_note);
    }

    public function test_without_create_follow_up_no_new_booking_is_created(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertRedirect();

        $this->assertSame(1, Booking::count());
    }

    public function test_referring_to_the_same_current_department_is_rejected(): void
    {
        $booking = $this->makeBooking(['dept' => 'labs']);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertSessionHasErrors('referral_to');
    }

    private function makeSurgeryService(array $attrs = []): Service
    {
        return Service::create(array_merge([
            'name' => 'عملية مياه بيضاء',
            'dept' => 'surgery',
            'price' => 3000,
            'one_eye_price' => 3000,
            'both_eyes_price' => 5000,
            'status' => 'active',
        ], $attrs));
    }

    public function test_follow_up_booking_carries_the_selected_service_and_eye(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
                'eye_side' => 'OD',
            ])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'surgery')->sole();
        $this->assertSame($service->id, $followUp->service_id);
        $this->assertSame($service->name, $followUp->service_name);
        $this->assertSame(EyeSide::OD, $followUp->eye_side);
        $this->assertSame('3000.00', $followUp->price);
    }

    public function test_both_eyes_referral_is_priced_with_the_both_eyes_price(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
                'eye_side' => 'OU',
            ])
            ->assertRedirect();

        $this->assertSame('5000.00', Booking::where('dept', 'surgery')->sole()->price);
    }

    public function test_surgery_referral_is_scheduled_with_the_service_and_eye(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
                'eye_side' => 'OS',
            ])
            ->assertRedirect();

        $surgery = Surgery::where('booking_id', Booking::where('dept', 'surgery')->sole()->id)->sole();
        $this->assertSame(EyeSide::OS, $surgery->eye);
        $this->assertSame($service->name, $surgery->procedure);
    }

    public function test_clinic_findings_are_carried_into_the_operation_pre_op_notes(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService();
        ClinicSheet::create([
            'booking_id' => $booking->id,
            'diagnosis' => 'إعتام عدسة',
            'visual_acuity_od' => '6/60',
            'visual_acuity_os' => '6/12',
            'iop_od' => 18,
            'iop_os' => 16,
            'plan' => 'استحلاب العدسة',
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
                'eye_side' => 'OD',
            ])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'surgery')->sole();
        $surgery = Surgery::where('booking_id', $followUp->id)->sole();

        $this->assertStringContainsString('إعتام عدسة', $surgery->pre_op_notes);
        $this->assertStringContainsString('6/60', $surgery->pre_op_notes);
        $this->assertStringContainsString('استحلاب العدسة', $surgery->pre_op_notes);
        $this->assertStringContainsString('إعتام عدسة', $followUp->visit_note);
    }

    public function test_insurance_payer_follows_the_referral_when_a_service_is_selected(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة تأمين', 'status' => 'active']);
        $booking = $this->makeBooking(['ins_company_id' => $company->id, 'pay_method' => 'insurance']);
        $service = $this->makeSurgeryService();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
                'eye_side' => 'OU',
            ])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'surgery')->sole();
        $this->assertSame($company->id, $followUp->ins_company_id);
        $this->assertSame(PayMethod::Insurance, $followUp->pay_method);
        $this->assertDatabaseHas('insurance_claims', [
            'booking_id' => $followUp->id,
            'insurance_company_id' => $company->id,
            'invoice_amount' => 5000,
        ]);
    }

    public function test_referral_without_a_service_stays_cash(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة تأمين', 'status' => 'active']);
        $booking = $this->makeBooking(['ins_company_id' => $company->id, 'pay_method' => 'insurance']);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs', 'create_follow_up' => true])
            ->assertRedirect();

        $followUp = Booking::where('dept', 'labs')->sole();
        $this->assertNull($followUp->ins_company_id);
        $this->assertSame(PayMethod::Cash, $followUp->pay_method);
    }

    public function test_service_from_another_department_is_rejected(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService(['dept' => 'labs', 'name' => 'تحليل']);

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
            ])
            ->assertSessionHasErrors('service_id');
    }

    public function test_eye_side_is_required_for_an_operation_service(): void
    {
        $booking = $this->makeBooking();
        $service = $this->makeSurgeryService();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", [
                'referral_to' => 'surgery',
                'create_follow_up' => true,
                'service_id' => $service->id,
            ])
            ->assertSessionHasErrors('eye_side');
    }

    public function test_invalid_eye_side_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'surgery', 'eye_side' => 'XX'])
            ->assertSessionHasErrors('eye_side');
    }

    public function test_patient_page_exposes_the_referral_services(): void
    {
        $booking = $this->makeBooking();
        $this->makeSurgeryService();
        $this->makeSurgeryService(['name' => 'خدمة معطلة', 'status' => 'inactive']);

        $this->actingAs($this->user)
            ->get("/clinic/{$booking->id}")
            ->assertInertia(fn ($page) => $page
                ->component('clinic/Patient')
                ->has('referral_services', 1)
                ->where('referral_services.0.name', 'عملية مياه بيضاء'),
            );
    }

    public function test_unauthorized_user_cannot_refer(): void
    {
        $booking = $this->makeBooking();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'labs'])
            ->assertForbidden();
    }

    public function test_invalid_referral_target_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->actingAs($this->user)
            ->post("/clinic/{$booking->id}/refer", ['referral_to' => 'not-a-dept'])
            ->assertSessionHasErrors('referral_to');
    }
}
