<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InsuranceBookingRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $overrides = []): void
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']));
        $user = User::factory()->create();
        $user->assignRole($role);

        $service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'clinic', 'price' => 5000, 'ins_price' => 5000]);
        $doctor = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);

        $overrides['service_ids'] = (($overrides['service_ids'] ?? null) === 'auto') ? [$service->id] : ($overrides['service_ids'] ?? null);

        $this->actingAs($user)->post('/booking', array_merge([
            'patient_name' => 'محمد', 'dept' => 'clinic', 'eye_side' => 'OD',
            'visit_date' => '2026-05-10', 'service_id' => $service->id, 'service_name' => $service->name,
            'doctor_id' => $doctor->id, 'price' => 5000, 'ins_company_id' => $company->id,
            'ins_amount' => 4000, 'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ], $overrides))->assertRedirect();
    }

    public function test_insurance_booking_posts_receivable_and_revenue_on_creation(): void
    {
        $this->book();

        $entry = JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->first();

        $this->assertNotNull($entry, 'no recognition entry posted');
        $this->assertEquals(4000, (float) $entry->amount);
    }

    public function test_labs_insurance_booking_posts_receivable_and_revenue_on_creation(): void
    {
        $this->book(['dept' => 'labs', 'doctor_id' => null]);

        $entry = JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->first();

        $this->assertNotNull($entry, 'no recognition entry posted for labs');
    }

    public function test_labs_multi_service_insurance_booking_posts_receivable_and_revenue(): void
    {
        $this->book(['dept' => 'labs', 'doctor_id' => null, 'service_ids' => 'auto']);

        $this->assertNotNull(
            JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->first(),
            'no recognition entry posted for multi-service labs booking',
        );
    }
}
