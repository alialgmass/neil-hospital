<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business rule: insurance doctor fees are a fixed amount per case, accrued
 * on completion to the doctor's own payable (Dr 5130 / Cr 22xx). The till is
 * credited only when the doctor is actually paid, so the fee leaves it once.
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

        $this->service = Service::create(['name' => 'فحص نظر', 'dept' => 'clinic', 'price' => 6500, 'ins_price' => 6500]);
        $this->doctor = Doctor::create(['name' => 'د. تأمين', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->doctor->services()->attach($this->service->id, ['fee' => 500]);
        $this->company = InsuranceCompany::create(['name' => 'شركة تأمين', 'coverage_pct' => 100]);
    }

    private function completeBooking(Booking $booking): void
    {
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'confirmed',
        ])->assertRedirect();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'in_progress',
        ])->assertRedirect();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'completed',
        ])->assertRedirect();
    }

    public function test_insurance_doctor_fee_accrues_to_the_doctor_on_completion_and_never_touches_the_till(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض تأمين', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_amount' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $insuranceDoctorFees = Account::where('code', '5130')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();

        $this->assertDatabaseMissing('journal_entries', [
            'reference' => $booking->file_no,
            'source' => 'insurance_doctor_payment',
        ]);

        $this->completeBooking($booking);

        $entry = JournalEntry::where('reference', $booking->file_no)
            ->where('source', 'insurance_doctor_payment')
            ->sole();

        $this->assertSame($insuranceDoctorFees->id, $entry->debit_account_id);
        $this->assertSame(app(SubledgerAccountResolver::class)->forDoctor($this->doctor->id), $entry->credit_account_id);
        $this->assertEquals(500.0, (float) $entry->amount);

        // The till is only credited when the doctor is actually paid.
        $this->assertSame(0, JournalEntry::where('reference', $booking->file_no)->where('credit_account_id', $cash->id)->count());
    }

    public function test_resaving_the_booking_unchanged_does_not_duplicate_the_cash_payment(): void
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض تأمين', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_amount' => 6500, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->first();

        $this->assertDatabaseMissing('journal_entries', [
            'reference' => $booking->file_no,
            'source' => 'insurance_doctor_payment',
        ]);

        $this->completeBooking($booking);

        $this->actingAs($this->user)->put("/booking/{$booking->id}", [
            'patient_name' => $booking->patient_name, 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_amount' => 6500, 'ins_company_id' => $this->company->id,
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
            'patient_name' => 'مريض تأمين', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->doctor->id, 'price' => 6500, 'ins_amount' => 6500, 'ins_company_id' => $this->company->id,
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
