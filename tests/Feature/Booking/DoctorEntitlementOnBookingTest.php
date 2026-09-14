<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorEntitlementOnBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $cataract;

    private Doctor $doctor;

    private InsuranceCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']));
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->cataract = Service::create([
            'name' => 'مياه بيضاء', 'dept' => 'clinic', 'price' => 5000, 'ins_price' => 5000,
        ]);
        $this->doctor = Doctor::create(['name' => 'د. عمر الجارحي', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->doctor->services()->attach($this->cataract->id, ['fee' => 750]);
        $this->company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);
    }

    private function book(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->post('/booking', array_merge([
            'patient_name' => 'محمد',
            'dept' => 'clinic',
            'visit_date' => '2026-05-10',
            'service_id' => $this->cataract->id,
            'service_name' => $this->cataract->name,
            'doctor_id' => $this->doctor->id,
            'price' => 5000,
            'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides));
    }

    public function test_a_normal_cash_booking_creates_no_entitlement(): void
    {
        $this->book(['pay_method' => 'cash'])->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }

    public function test_an_insurance_booking_creates_a_pending_entitlement_at_the_doctors_service_fee(): void
    {
        $this->book(['pay_method' => 'insurance'])->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 1);
        $this->assertDatabaseHas('doctor_entitlements', [
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->cataract->id,
            'amount' => 750,
            'source' => 'insurance',
            'status' => 'pending',
        ]);
    }

    public function test_a_contract_booking_creates_a_contract_sourced_entitlement(): void
    {
        $this->book(['pay_method' => 'contract'])->assertRedirect();

        $this->assertDatabaseHas('doctor_entitlements', [
            'amount' => 750,
            'source' => 'contract',
        ]);
    }

    public function test_the_entitlement_falls_back_to_the_service_default_fee(): void
    {
        $this->doctor->services()->detach();
        $this->cataract->update(['default_dr_fee' => 600]);

        $this->book(['pay_method' => 'insurance'])->assertRedirect();

        $this->assertDatabaseHas('doctor_entitlements', ['amount' => 600]);
    }

    public function test_no_entitlement_and_a_warning_when_no_fee_can_be_resolved(): void
    {
        $this->doctor->services()->detach();

        $this->book(['pay_method' => 'insurance'])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }

    public function test_an_insurance_booking_without_a_company_is_rejected(): void
    {
        $this->book(['pay_method' => 'insurance', 'ins_company_id' => ''])
            ->assertSessionHasErrors('ins_company_id');

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }

    public function test_the_entitlement_is_created_with_an_insurance_company_selected(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة X', 'coverage_pct' => 80]);

        $this->book(['pay_method' => 'insurance', 'ins_company_id' => $company->id])->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 1);
    }

    public function test_no_entitlement_when_the_booking_has_no_doctor(): void
    {
        $this->book(['pay_method' => 'insurance', 'doctor_id' => null])->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }

    public function test_no_entitlement_when_the_booking_has_no_service(): void
    {
        // A service is mandatory for an insurance booking, so use a contract deal
        // (also third-party) to exercise the "no service → no entitlement" path.
        $this->book([
            'pay_method' => 'contract',
            'ins_company_id' => '',
            'service_id' => null,
            'service_name' => null,
        ])->assertRedirect();

        $this->assertDatabaseCount('doctor_entitlements', 0);
    }
}
