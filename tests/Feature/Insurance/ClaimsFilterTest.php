<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClaimsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'insurance.view', 'guard_name' => 'web']));
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
    }

    private function makeClaim(array $overrides = []): InsuranceClaim
    {
        $company = InsuranceCompany::firstOrCreate(
            ['name' => $overrides['company_name'] ?? 'شركة أ'],
            ['status' => 'active']
        );

        $service = Service::create([
            'name' => 'خدمة-'.uniqid(), 'dept' => $overrides['dept'] ?? 'clinic', 'price' => 1000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 400, 'dr_share' => 600,
        ]);

        $doctor = isset($overrides['doctor_id']) ? Doctor::find($overrides['doctor_id']) : null;

        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => $overrides['patient_name'] ?? 'مريض',
            'dept' => $overrides['dept'] ?? 'clinic', 'doctor_id' => $doctor?->id,
            'visit_date' => now()->toDateString(), 'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $company->id,
            'service_id' => $service->id,
            'patient_name' => $overrides['patient_name'] ?? 'مريض',
            'file_no' => $booking->file_no,
            'service_name' => $service->name,
            'invoice_amount' => 1000,
            'insurance_share' => 800,
            'patient_share' => 200,
            'status' => $overrides['status'] ?? 'draft',
            'service_date' => now()->toDateString(),
            'claim_date' => $overrides['claim_date'] ?? now()->toDateString(),
        ]);
    }

    public function test_filter_by_status(): void
    {
        $this->makeClaim(['status' => 'draft']);
        $this->makeClaim(['status' => 'submitted']);

        $this->get('/insurance?status=submitted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('insurance/Companies')
                ->where('claims.data.0.status', 'submitted')
                ->has('claims.data', 1)
            );
    }

    public function test_filter_by_date_range(): void
    {
        $this->makeClaim(['claim_date' => '2026-01-05']);
        $this->makeClaim(['claim_date' => '2026-03-15']);

        $this->get('/insurance?from=2026-03-01&to=2026-03-31')
            ->assertInertia(fn ($page) => $page
                ->where('claims.data.0.claim_date', fn ($v) => str_starts_with($v, '2026-03-15'))
                ->has('claims.data', 1)
            );
    }

    public function test_combined_filters_apply_as_and(): void
    {
        $companyA = InsuranceCompany::create(['name' => 'شركة X', 'status' => 'active']);
        InsuranceCompany::create(['name' => 'شركة Y', 'status' => 'active']);

        $this->makeClaim(['company_name' => 'شركة X', 'status' => 'draft']);
        $this->makeClaim(['company_name' => 'شركة X', 'status' => 'submitted']);
        $this->makeClaim(['company_name' => 'شركة Y', 'status' => 'submitted']);

        $this->get("/insurance?company_id={$companyA->id}&status=submitted")
            ->assertInertia(fn ($page) => $page
                ->has('claims.data', 1)
                ->where('claims.data.0.insurance_company_id', $companyA->id)
                ->where('claims.data.0.status', 'submitted')
            );
    }

    public function test_filter_by_dept(): void
    {
        $this->makeClaim(['dept' => 'clinic']);
        $this->makeClaim(['dept' => 'labs']);

        $this->get('/insurance?dept=labs')
            ->assertInertia(fn ($page) => $page->has('claims.data', 1));
    }
}
