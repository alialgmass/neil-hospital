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
 * Business rule: عندما patient_paid = 0، سعر الخدمة (بعد خصم رسم خزنة
 * التطوير) يصبح دينًا على الطبيب، يُخصم من مستحقاته القادمة.
 */
class DoctorDebtOnUnpaidBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $service;

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

        $this->doctor = Doctor::create(['name' => 'د. مدين', 'fee_type' => 'percentage', 'fee_value' => 40]);

        $this->service = Service::create([
            'name' => 'كشف غير مدفوع', 'dept' => 'clinic', 'price' => 1000,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 400, 'dr_share' => 600,
            'dev_treasury_fee' => 50,
        ]);
    }

    public function test_creating_an_unpaid_cash_booking_adds_debt_on_the_doctor(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض بلا دفع', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id,
            'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        // Debt = price (1000) - dev_treasury_fee (50) = 950.
        $this->assertEquals(950.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    /**
     * Regression test: an insurance-fee-type doctor was still getting debt
     * recorded on an unpaid cash booking, but DoctorClaimsService::
     * computeShareForPayment() always returns 0 for them — so the debt could
     * never be settled back no matter how much got paid later. It has to be
     * excluded at the point debt is incurred, not just at settlement time.
     */
    public function test_insurance_fee_type_doctor_never_incurs_debt_even_on_an_unpaid_cash_booking(): void
    {
        $insuranceDoctor = Doctor::create(['name' => 'د. تأمين', 'fee_type' => 'insurance', 'fee_value' => 0]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض بلا دفع', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $insuranceDoctor->id,
            'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $insuranceDoctor->fresh()->doctor_debt_balance);
    }

    public function test_fully_paid_booking_creates_no_debt(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض دافع', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id,
            'price' => 1000, 'paid_amount' => 1000,
            'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }

    public function test_future_dues_are_settled_against_outstanding_debt(): void
    {
        // Booking #1: unpaid → doctor owes 950.
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض 1', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id,
            'price' => 1000, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $this->assertEquals(950.0, (float) $this->doctor->fresh()->doctor_debt_balance);

        // Booking #2: fully paid cash → raw doctor share = 40% of (1000-50) = 380,
        // entirely absorbed by the 950 debt, so nothing is posted to doctor dues
        // and the debt balance drops to 950 - 380 = 570.
        $booking2 = Booking::create([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'مريض 2',
            'dept' => 'clinic',
            'doctor_id' => $this->doctor->id,
            'visit_date' => '2026-05-11',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'price' => 0,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 0,
            'pay_method' => 'cash',
            'pay_status' => 'unpaid',
            'status' => 'waiting',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)->patch("/booking/{$booking2->id}/pay", [
            'price' => 1000, 'paid_amount' => 1000, 'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(0, JournalEntry::where('idempotency_key', "doctor_dues:{$booking2->file_no}:1000")->count());
        $this->assertEquals(570.0, (float) $this->doctor->fresh()->doctor_debt_balance);
    }
}
