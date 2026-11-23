<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\LedgerService;
use Modules\Booking\Actions\UpdateBookingStatusAction;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\InsuranceCompany;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorClaimsService;
use Modules\Insurance\Models\InsuranceClaim;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepairInsurancePostingGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'drpayments.write'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function bookInsurance(string $dept, array $overrides = []): Booking
    {
        $service = Service::create(['name' => 'خدمة', 'dept' => $dept, 'price' => 5000, 'ins_price' => 5000]);
        $this->doctor = Doctor::create(['name' => 'د. أحمد', 'fee_type' => 'fixed', 'fee_value' => 0, 'is_active' => true]);
        $this->doctor->services()->attach($service->id, ['fee' => 750]);
        $company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);

        $this->actingAs($this->user)->post('/booking', array_merge([
            'patient_name' => 'محمد', 'dept' => $dept, 'eye_side' => 'OD', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $this->doctor->id,
            'price' => 5000, 'ins_company_id' => $company->id, 'ins_amount' => 4000,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return Booking::firstOrFail();
    }

    private function payDoctor(float $amount): void
    {
        $this->actingAs($this->user)->post('/dr-claims/pay', [
            'doctor_id' => $this->doctor->id, 'amount' => $amount, 'paid_at' => today()->toDateString(), 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function doctorPayableBalance(): float
    {
        $code = Account::findOrFail($this->doctor->fresh()->payable_account_id)->code;

        return (float) collect(app(LedgerService::class)->trialBalance())->firstWhere('code', $code)['balance'];
    }

    private function runRepair(): void
    {
        (require database_path('migrations/2026_11_15_190850_repair_labs_insurance_shares_and_settled_doctor_accruals.php'))->up();
    }

    public function test_insurance_dues_paid_before_completion_are_accrued_when_the_booking_completes(): void
    {
        $booking = $this->bookInsurance('clinic');

        $this->assertTrue(app(DoctorClaimsService::class)->calculateClaims($this->doctor->id, null, null)['rows'][0]['is_insurance']);

        $this->payDoctor(750);
        $this->assertEquals(-750.0, $this->doctorPayableBalance());

        foreach (['confirmed', 'in_progress', 'completed'] as $status) {
            app(UpdateBookingStatusAction::class)->execute($booking->id, $status);
        }

        $this->assertEquals(0.0, $this->doctorPayableBalance());
        $this->assertSame(1, JournalEntry::where('source', 'insurance_doctor_payment')->count());
    }

    public function test_repair_accrues_settled_dues_of_already_completed_bookings_once(): void
    {
        $booking = $this->bookInsurance('clinic');
        $this->payDoctor(750);
        DB::table('bookings')->where('id', $booking->id)->update(['status' => 'completed']);

        $this->runRepair();
        $this->runRepair();

        $this->assertEquals(0.0, $this->doctorPayableBalance());
        $this->assertSame(1, JournalEntry::where('source', 'insurance_doctor_payment')->count());
    }

    public function test_repair_leaves_settled_dues_of_uncompleted_bookings_alone(): void
    {
        $this->bookInsurance('clinic');
        $this->payDoctor(750);

        $this->runRepair();

        $this->assertSame(0, JournalEntry::where('source', 'insurance_doctor_payment')->count());
    }

    public function test_repair_restores_the_insurer_share_of_labs_bookings_saved_with_zero(): void
    {
        $booking = $this->bookInsurance('labs', ['ins_amount' => 0, 'doctor_id' => null]);
        $this->assertSame(0, JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->count());

        $this->runRepair();
        $this->runRepair();

        $entry = JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->sole();
        $this->assertEquals(4000.0, (float) $entry->amount);
        $this->assertEquals(4000.0, (float) $booking->fresh()->ins_amount);
        $this->assertEquals(1000.0, (float) InsuranceClaim::sole()->patient_share);
    }

    public function test_repair_skips_labs_bookings_the_patient_already_paid_in_full(): void
    {
        $this->bookInsurance('labs', ['ins_amount' => 0, 'doctor_id' => null, 'paid_amount' => 5000, 'pay_status' => 'paid']);

        $this->runRepair();

        $this->assertSame(0, JournalEntry::where('idempotency_key', 'like', 'insurance_claim_submit:%')->count());
        $this->assertEquals(0.0, (float) Booking::firstOrFail()->ins_amount);
    }
}
