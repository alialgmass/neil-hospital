<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractDealTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit', 'booking.pay'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 500, 'ins_price' => 500,
        ]);
    }

    public function test_pay_method_enum_exposes_contract_with_arabic_label(): void
    {
        $this->assertSame('contract', PayMethod::Contract->value);
        $this->assertSame('تعاقد', PayMethod::Contract->label());
    }

    public function test_a_booking_can_be_created_with_the_contract_deal_type(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-05-01',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'price' => 500,
            'pay_method' => 'contract',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'patient_name' => 'محمد علي',
            'pay_method' => 'contract',
        ]);
    }

    public function test_a_booking_can_be_switched_to_the_contract_deal_type_on_edit(): void
    {
        $booking = Booking::create([
            'file_no' => 'MRN-CT-1',
            'patient_name' => 'سعاد حسن',
            'dept' => 'clinic',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'visit_date' => '2026-05-01',
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name,
            'dept' => 'clinic',
            'visit_date' => '2026-05-01',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'price' => 500,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'contract',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
        ])->assertRedirect();

        $this->assertSame(PayMethod::Contract, $booking->fresh()->pay_method);
    }
}
