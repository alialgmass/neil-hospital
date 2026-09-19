<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\States\DraftState;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreBookingInsuranceClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.create', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 500, 'ins_price' => 500,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'patient_name' => 'أحمد سمير',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ], $overrides);
    }

    public function test_insurance_booking_creates_a_draft_claim_immediately(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80]);

        $this->actingAs($this->user)->post('/booking', $this->payload([
            'ins_company_id' => $company->id,
            'ins_amount' => 400,
            'paid_amount' => 100,
            'pay_method' => 'insurance',
            'pay_status' => 'paid',
        ]))->assertRedirect();

        $claim = InsuranceClaim::first();
        $this->assertNotNull($claim, 'An insurance booking must generate a claim on creation.');
        $this->assertSame($company->id, $claim->insurance_company_id);
        $this->assertTrue($claim->status->equals(DraftState::class));
        $this->assertEquals(400.0, (float) $claim->insurance_share);
        $this->assertEquals(100.0, (float) $claim->patient_share);
    }

    public function test_insurance_booking_without_a_company_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/booking', $this->payload([
            'pay_method' => 'insurance',
            'pay_status' => 'unpaid',
        ]))->assertSessionHasErrors('ins_company_id');

        $this->assertSame(0, InsuranceClaim::count());
    }

    public function test_cash_booking_does_not_create_a_claim(): void
    {
        $this->actingAs($this->user)->post('/booking', $this->payload())->assertRedirect();

        $this->assertSame(0, InsuranceClaim::count());
    }
}
