<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\ReverseBookingPaymentAction;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Actions\UpdateSurgeryStatusAction;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The day's entries the accountant flagged as wrong: a private (cash)
 * surgery, a clinic visit collected at booking, and the development-fund
 * transfer missing from the treasury screen.
 */
class DailyEntriesCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['booking.create', 'booking.edit', 'booking.pay'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_cash_surgery_accrues_the_net_doctor_share_once_on_completion(): void
    {
        $doctor = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $service = Service::create(['name' => 'مياه بيضاء', 'dept' => 'surgery', 'price' => 9000, 'dev_treasury_fee' => 50]);
        $booking = Booking::create([
            'file_no' => 'MRN-SURG', 'patient_name' => 'مريض', 'dept' => 'surgery', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $doctor->id,
            'price' => 9000, 'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);
        $surgery = Surgery::create(['booking_id' => $booking->id, 'surgeon_id' => $doctor->id, 'dept' => 'surgery', 'status' => 'in_progress']);

        // The patient pays before the supplies are recorded on the case.
        $this->actingAs($this->user)
            ->patch("/booking/{$booking->id}/pay", ['paid_amount' => 9000, 'pay_method' => 'cash'])
            ->assertRedirect();

        $this->assertSame(0, $this->doctorDues($booking)->count(), 'no gross accrual before supplies are known');

        $surgery->update(['supply_total' => 2385]);
        app(UpdateSurgeryStatusAction::class)->execute($surgery->id, 'completed');

        $entry = $this->doctorDues($booking)->sole();
        $this->assertEquals(6565.0, (float) $entry->amount); // 9,000 − 2,385 − 50
        $this->assertSame(Account::where('code', '5120')->value('id'), $entry->debit_account_id);
        $this->assertSame(app(SubledgerAccountResolver::class)->forDoctor($doctor->id), $entry->credit_account_id);
        $this->assertSame(0, JournalEntry::where('reference', $booking->file_no)->whereNotNull('reversed_at')->count());
        $this->assertSame(0, JournalEntry::where('credit_account_id', Account::where('code', '4070')->value('id'))->count());
    }

    public function test_clinic_booking_collected_at_creation_accrues_the_doctor_share(): void
    {
        $doctor = Doctor::create(['name' => 'د. عيادة', 'fee_type' => 'percentage', 'fee_value' => 50]);
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 200]);

        $this->actingAs($this->user)->post('/booking', [
            'patient_name' => 'مريض عيادة', 'dept' => 'clinic', 'eye_side' => 'OD', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name, 'doctor_id' => $doctor->id,
            'price' => 200, 'paid_amount' => 200, 'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ])->assertRedirect();

        $booking = Booking::latest('id')->firstOrFail();

        $entry = $this->doctorDues($booking)->sole();
        $this->assertEquals(100.0, (float) $entry->amount);
        $this->assertSame(Account::where('code', '5110')->value('id'), $entry->debit_account_id);
        $this->assertSame(app(SubledgerAccountResolver::class)->forDoctor($doctor->id), $entry->credit_account_id);
    }

    public function test_development_fee_shows_on_the_treasury_screen_and_is_returned_on_cancellation(): void
    {
        $service = Service::create(['name' => 'كشف', 'dept' => 'clinic', 'price' => 300, 'dev_treasury_fee' => 50]);
        $booking = Booking::create([
            'file_no' => 'MRN-DEV', 'patient_name' => 'مريض', 'dept' => 'clinic', 'visit_date' => today()->toDateString(),
            'service_id' => $service->id, 'service_name' => $service->name,
            'price' => 300, 'paid_amount' => 0, 'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        $this->actingAs($this->user)
            ->patch("/booking/{$booking->id}/pay", ['paid_amount' => 300, 'pay_method' => 'cash'])
            ->assertRedirect();

        $outflow = TreasuryEntry::where('booking_id', $booking->id)->where('type', TreasuryType::Out->value)->sole();
        $this->assertEquals(50.0, (float) $outflow->amount);
        $this->assertTreasuryMatchesCashLedger();

        app(ReverseBookingPaymentAction::class)->execute($booking->fresh());

        $this->assertNotNull($outflow->fresh()->reversed_at);
        $this->assertTreasuryMatchesCashLedger();
    }

    private function doctorDues(Booking $booking): Builder
    {
        return JournalEntry::where('reference', $booking->file_no)
            ->where('source', JournalSource::DOCTOR_SHIFT->value)
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id');
    }

    private function assertTreasuryMatchesCashLedger(): void
    {
        $cashId = Account::where('code', '1010')->value('id');
        $ledger = (float) JournalEntry::where('debit_account_id', $cashId)->sum('amount')
            - (float) JournalEntry::where('credit_account_id', $cashId)->sum('amount');
        $screen = (float) TreasuryEntry::where('type', TreasuryType::In->value)->sum('amount')
            - (float) TreasuryEntry::where('type', TreasuryType::Out->value)->sum('amount');

        $this->assertEqualsWithDelta($ledger, $screen, 0.001);
    }
}
