<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: insurance doctor fees are a fixed amount per case, paid
 * cash immediately (Dr 5130 / Cr 1010) — never accrued to 2010 (doctor
 * payable), unlike every cash department.
 */
class InsuranceDoctorCashPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    private Service $service;

    private InsuranceCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'فحص نظر', 'dept' => 'labs', 'price' => 6500, 'ins_price' => 6500]);
        $this->doctor = Doctor::create(['name' => 'د. تأمين', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->doctor->services()->attach($this->service->id, ['fee' => 500]);
        $this->company = InsuranceCompany::create(['name' => 'شركة تأمين', 'coverage_pct' => 100]);
    }

    public function test_insurance_doctor_fee_posts_cash_and_never_touches_doctor_payable(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض تأمين', 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $insuranceDoctorFees = Account::where('code', '5130')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();
        $doctorPayable = Account::where('code', '2010')->firstOrFail();

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('source', 'insurance_doctor_payment')
            ->sole();

        $this->assertSame($insuranceDoctorFees->id, $entry->debit_account_id);
        $this->assertSame($cash->id, $entry->credit_account_id);
        $this->assertEquals(500.0, (float) $entry->amount);

        // 2010 (doctor payable) balance untouched by this posting.
        $this->assertEquals(0.0, (float) $doctorPayable->fresh()->balance);
    }

    public function test_resaving_the_booking_unchanged_does_not_duplicate_the_cash_payment(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض تأمين', 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name, 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $count = JournalEntry::where('reference', $booking->file_no)
            ->where('source', 'insurance_doctor_payment')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_cancelling_the_booking_reverses_the_cash_payment(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض تأمين', 'dept' => 'labs', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'cancelled', 'cancel_reason' => 'x',
        ])->assertRedirect();

        $live = JournalEntry::where('reference', $booking->file_no)
            ->where('source', 'insurance_doctor_payment')
            ->whereNull('reversed_at')
            ->first();

        $this->assertNull($live);
    }
}
