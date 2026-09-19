<?php

namespace Tests\Feature\Booking;

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

class DoctorEntitlementAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Service $service;

    private Doctor $omar;

    private Doctor $sara;

    private InsuranceCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit', 'booking.delete'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'clinic', 'price' => 5000, 'ins_price' => 5000]);
        $this->omar = Doctor::create(['name' => 'د. عمر', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->omar->services()->attach($this->service->id, ['fee' => 750]);
        $this->sara = Doctor::create(['name' => 'د. سارة', 'fee_type' => 'fixed', 'fee_value' => 0]);
        $this->sara->services()->attach($this->service->id, ['fee' => 900]);
        $this->company = InsuranceCompany::create(['name' => 'شركة التأمين', 'coverage_pct' => 80]);
    }

    /**
     * Insurance doctor fees post to 5130 (INSURANCE_DOCTOR_FEES), paid cash
     * immediately — never 5110/2010, which is now reserved for Contract
     * bookings (a different third-party payer type the spec doesn't touch).
     */
    private function drExpenseId(): string
    {
        return Account::where('code', '5130')->value('id');
    }

    private function cashId(): string
    {
        return Account::where('code', '1010')->value('id');
    }

    private function payableId(): string
    {
        return Account::where('code', '2010')->value('id');
    }

    private function createInsuranceBooking(): Booking
    {
        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->omar->id, 'price' => 5000, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ])->assertRedirect();

        return Booking::latest('id')->first();
    }

    private function editBooking(Booking $booking, array $overrides): void
    {
        $this->actingAs($this->user)->put("/booking/{$booking->id}", array_merge([
            'patient_name' => $booking->patient_name, 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => '2026-05-10',
            'service_id' => $this->service->id, 'service_name' => $this->service->name,
            'doctor_id' => $this->omar->id, 'price' => 5000, 'ins_company_id' => $this->company->id,
            'pay_method' => 'insurance', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ], $overrides))->assertRedirect();
    }

    private function liveAccrual(Booking $booking): ?JournalEntry
    {
        return JournalEntry::where('reference', $booking->file_no)
            ->where('debit_account_id', $this->drExpenseId())
            ->where('idempotency_key', 'like', 'doctor_entitlement:%')
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->first();
    }

    public function test_creating_an_insurance_booking_posts_the_doctor_cash_payment_once(): void
    {
        $booking = $this->createInsuranceBooking();

        $entry = $this->liveAccrual($booking);
        $this->assertNotNull($entry);
        $this->assertEquals(750.0, (float) $entry->amount);
        $this->assertSame($this->cashId(), $entry->credit_account_id);
        $this->assertNotSame($this->payableId(), $entry->credit_account_id);
    }

    public function test_re_saving_the_booking_unchanged_posts_no_new_entry(): void
    {
        $booking = $this->createInsuranceBooking();
        $this->editBooking($booking, []);
        $this->editBooking($booking, []);

        $count = JournalEntry::where('reference', $booking->file_no)
            ->where('idempotency_key', 'like', 'doctor_entitlement:%')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_recalculation_reverses_the_old_accrual_and_posts_the_new_amount(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, ['doctor_id' => $this->sara->id]);

        $live = $this->liveAccrual($booking);
        $this->assertNotNull($live);
        $this->assertEquals(900.0, (float) $live->amount);

        // Original 750 entry is now reversed
        $this->assertDatabaseHas('journal_entries', [
            'reference' => $booking->file_no,
            'amount' => 750,
        ]);
        $original = JournalEntry::where('reference', $booking->file_no)
            ->where('amount', 750)
            ->where('idempotency_key', 'like', 'doctor_entitlement:%')
            ->first();
        $this->assertNotNull($original->reversed_at);
    }

    public function test_switching_away_from_insurance_reverses_the_accrual(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->editBooking($booking, ['pay_method' => 'cash']);

        $this->assertNull($this->liveAccrual($booking));
    }

    public function test_cancelling_the_booking_reverses_the_accrual(): void
    {
        $booking = $this->createInsuranceBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/status", [
            'status' => 'cancelled', 'cancel_reason' => 'x',
        ])->assertRedirect();

        $this->assertNull($this->liveAccrual($booking));
    }
}
