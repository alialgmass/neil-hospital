<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: رسوم خزنة التطوير تُخصم من سعر الخدمة قبل حساب أجر الطبيب.
 * Example from the spec: price 1000, dev fee 50 → doctor's share is computed
 * on 950, not 1000.
 */
class DevTreasuryDeductionBeforeDoctorFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.pay'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->doctor = Doctor::create([
            'name' => 'د. أحمد',
            'fee_type' => 'percentage',
            'fee_value' => 40,
        ]);
    }

    private function createBooking(Service $service, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض',
            'dept' => 'clinic',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-04-20',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'price' => 0,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_doctor_percentage_fee_is_computed_on_price_net_of_dev_fee(): void
    {
        $service = Service::create([
            'name' => 'كشف عام', 'dept' => 'clinic', 'price' => 1000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 400, 'dr_share' => 600,
            'dev_treasury_fee' => 50,
        ]);
        $booking = $this->createBooking($service);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 1000, 'paid_amount' => 1000, 'pay_method' => 'cash',
        ])->assertRedirect();

        // 40% of (1000 - 50) = 380, not 40% of 1000 = 400.
        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', "doctor_dues:{$booking->file_no}:1000")
            ->sole();

        $this->assertEquals(380.0, (float) $entry->amount);

        // The dev-treasury transfer itself still posts its own fixed 50.
        $devEntry = JournalEntry::where('idempotency_key', "development_fee:{$booking->file_no}")->sole();
        $this->assertEquals(50.0, (float) $devEntry->amount);
    }

    public function test_fixed_doctor_fee_is_unaffected_by_dev_fee(): void
    {
        $service = Service::create([
            'name' => 'عملية بسيطة', 'dept' => 'clinic', 'price' => 1000,
            'center_type' => 'fixed', 'center_val' => 200, 'center_share' => 200, 'dr_share' => 800,
            'dev_treasury_fee' => 50,
        ]);
        $doctor = Doctor::create(['name' => 'د. ثابت', 'fee_type' => 'fixed', 'fee_value' => 300]);
        $booking = $this->createBooking($service, ['doctor_id' => $doctor->id]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 1000, 'paid_amount' => 1000, 'pay_method' => 'cash',
        ])->assertRedirect();

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', "doctor_dues:{$booking->file_no}:1000")
            ->sole();

        // Fixed fee amount is untouched by the dev-treasury deduction.
        $this->assertEquals(300.0, (float) $entry->amount);
    }

    public function test_dev_fee_is_deducted_only_once_across_installments(): void
    {
        $service = Service::create([
            'name' => 'كشف بالتقسيط', 'dept' => 'clinic', 'price' => 1000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 400, 'dr_share' => 600,
            'dev_treasury_fee' => 50,
        ]);
        $booking = $this->createBooking($service);

        // First installment: 400 paid, base netted = 400 - 50 = 350 → 40% = 140.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 1000, 'paid_amount' => 400, 'pay_method' => 'cash',
        ])->assertRedirect();

        $first = JournalEntry::where('idempotency_key', "doctor_dues:{$booking->file_no}:400")->sole();
        $this->assertEquals(140.0, (float) $first->amount);

        // Second installment: 600 paid, no further dev-fee deduction → 40% of 600 = 240.
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 1000, 'paid_amount' => 600, 'pay_method' => 'cash',
        ])->assertRedirect();

        $second = JournalEntry::where('idempotency_key', "doctor_dues:{$booking->file_no}:1000")->sole();
        $this->assertEquals(240.0, (float) $second->amount);

        // Dev-treasury fee itself still posted exactly once.
        $this->assertSame(1, JournalEntry::where('idempotency_key', "development_fee:{$booking->file_no}")->count());
    }
}
