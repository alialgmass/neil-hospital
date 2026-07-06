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

class SalesInvoiceInsuranceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(['name' => 'booking.edit', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createBooking(array $overrides = []): Booking
    {
        $service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 500, 'ins_price' => 500,
        ]);

        return Booking::create(array_merge([
            'file_no' => 'MRN-001',
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'service_id' => $service->id,
            'service_name' => $service->name,
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

    public function test_selecting_insurance_company_saves_amount_and_creates_claim(): void
    {
        $booking = $this->createBooking();
        $company = InsuranceCompany::create([
            'name' => 'شركة التأمين الأهلية', 'coverage_pct' => 80,
        ]);

        $this->actingAs($this->user)
            ->post('/sales-invoices', [
                'booking_id' => $booking->id,
                'amount_paid' => 100,
                'pay_method' => 'insurance',
                'ins_company_id' => $company->id,
                'ins_amount' => 400,
                'discount' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'ins_company_id' => $company->id,
            'ins_amount' => 400,
            'pay_method' => 'insurance',
        ]);

        $claim = InsuranceClaim::where('booking_id', $booking->id)->first();

        $this->assertNotNull($claim, 'Insurance claim should be created when paying via insurance');
        $this->assertSame($company->id, $claim->insurance_company_id);
        $this->assertEquals(400.0, (float) $claim->insurance_share);
        $this->assertEquals(100.0, (float) $claim->patient_share);
    }

    public function test_insurance_company_is_required_when_pay_method_is_insurance(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)
            ->post('/sales-invoices', [
                'booking_id' => $booking->id,
                'amount_paid' => 100,
                'pay_method' => 'insurance',
                'ins_amount' => 400,
                'discount' => 0,
            ])
            ->assertSessionHasErrors('ins_company_id');

        $this->assertDatabaseMissing('insurance_claims', ['booking_id' => $booking->id]);
    }
}
