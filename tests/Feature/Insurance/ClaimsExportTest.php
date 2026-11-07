<?php

namespace Tests\Feature\Insurance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClaimsExportTest extends TestCase
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

    private function makeClaim(string $claimDate): InsuranceClaim
    {
        $company = InsuranceCompany::firstOrCreate(['name' => 'شركة أ'], ['status' => 'active']);
        $service = Service::create(['name' => 'خدمة-'.uniqid(), 'dept' => 'clinic', 'price' => 1000]);
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض',
            'dept' => 'clinic', 'visit_date' => $claimDate, 'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return InsuranceClaim::create([
            'booking_id' => $booking->id,
            'insurance_company_id' => $company->id,
            'service_id' => $service->id,
            'patient_name' => 'مريض',
            'file_no' => $booking->file_no,
            'service_name' => $service->name,
            'invoice_amount' => 1000,
            'insurance_share' => 800,
            'patient_share' => 200,
            'status' => 'draft',
            'service_date' => $claimDate,
            'claim_date' => $claimDate,
        ]);
    }

    public function test_export_downloads_an_xlsx_file_for_the_selected_month(): void
    {
        $this->makeClaim('2026-03-15');
        $this->makeClaim('2026-04-01');

        $response = $this->get('/insurance/claims/export?month=2026-03');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_can_be_scoped_to_a_company(): void
    {
        $claim = $this->makeClaim('2026-03-15');

        $response = $this->get("/insurance/claims/export?month=2026-03&company_id={$claim->insurance_company_id}");

        $response->assertOk();
    }
}
