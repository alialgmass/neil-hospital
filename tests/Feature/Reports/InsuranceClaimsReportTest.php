<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Inventory\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InsuranceClaimsReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private InsuranceCompany $company;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'reports.financial', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('reports.financial');

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->company = InsuranceCompany::create(['name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80]);
        $this->service = Service::create(['name' => 'كشف عيون', 'dept' => 'clinic', 'price' => 300, 'ins_price' => 300, 'status' => 'active']);
    }

    private function makeClaim(string $status, string $claimDate = '2026-06-20'): InsuranceClaim
    {
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض تأمين',
            'dept' => 'clinic',
            'visit_date' => $claimDate,
            'price' => 300,
            'discount' => 0,
            'ins_amount' => 240,
            'paid_amount' => 60,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
            'status' => 'completed',
            'ins_company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);

        return InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $this->company->id,
            'service_id' => $this->service->id,
            'patient_name' => 'مريض تأمين',
            'service_name' => 'كشف عيون',
            'invoice_amount' => 300,
            'patient_share' => 60,
            'insurance_share' => 240,
            'status' => $status,
            'service_date' => $claimDate,
            'claim_date' => $claimDate,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_status_breakdown_counts_and_totals_claims_per_status(): void
    {
        $this->makeClaim('draft');
        $this->makeClaim('submitted');
        $this->makeClaim('submitted');
        $this->makeClaim('approved');
        $this->makeClaim('paid');

        $response = $this->actingAs($this->user)->get('/reports/insurance?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.statusBreakdown', fn ($rows) => collect($rows)->firstWhere('status', 'submitted')['count'] === 2
                && collect($rows)->firstWhere('status', 'submitted')['total'] === 480
                && collect($rows)->firstWhere('status', 'draft')['count'] === 1
                && collect($rows)->firstWhere('status', 'rejected')['count'] === 0));
    }

    public function test_status_breakdown_respects_date_range(): void
    {
        $this->makeClaim('paid', '2026-05-15');
        $this->makeClaim('paid', '2026-06-20');

        $response = $this->actingAs($this->user)->get('/reports/insurance?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.statusBreakdown', fn ($rows) => collect($rows)->firstWhere('status', 'paid')['count'] === 1));
    }
}
