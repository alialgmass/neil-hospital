<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayBookingAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $permission = Permission::firstOrCreate(['name' => 'booking.pay', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
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

    public function test_cash_payment_debits_cash_and_credits_dept_revenue(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'cash',
        ]);

        $cash = Account::where('code', '1010')->firstOrFail();
        $revenue = Account::where('code', '4010')->firstOrFail();

        $entry = JournalEntry::where('reference', $booking->file_no)->sole();
        $this->assertSame($cash->id, $entry->debit_account_id);
        $this->assertSame($revenue->id, $entry->credit_account_id);
        $this->assertEquals(300.00, (float) $entry->amount);
    }

    public function test_card_payment_debits_bank(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'card',
        ]);

        $bank = Account::where('code', '1020')->firstOrFail();
        $entry = JournalEntry::where('reference', $booking->file_no)->sole();
        $this->assertSame($bank->id, $entry->debit_account_id);
    }

    public function test_insurance_payment_posts_no_journal_entry(): void
    {
        $booking = $this->createBooking();

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'insurance',
        ]);

        $this->assertSame(0, JournalEntry::where('reference', $booking->file_no)->count());
    }

    public function test_service_revenue_account_override_is_used_when_valid(): void
    {
        // Reuse the seeded 4060 (Retina Revenue) account as the override target.
        $override = Account::where('code', '4060')->firstOrFail();

        $service = Service::create([
            'name' => 'شبكية', 'dept' => 'clinic', 'price' => 500,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 200, 'dr_share' => 300,
            'revenue_account_id' => $override->id,
        ]);

        $booking = $this->createBooking(['service_id' => $service->id]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500, 'paid_amount' => 500, 'pay_method' => 'cash',
        ]);

        $entry = JournalEntry::where('reference', $booking->file_no)->sole();
        $this->assertSame($override->id, $entry->credit_account_id);
    }

    public function test_invalid_service_revenue_override_falls_back_to_dept_account(): void
    {
        // Points at a non-revenue account — must be rejected and fall back.
        $badOverride = Account::where('code', '1010')->firstOrFail();

        $service = Service::create([
            'name' => 'كشف', 'dept' => 'clinic', 'price' => 500,
            'center_type' => 'pct', 'center_val' => 40, 'center_share' => 200, 'dr_share' => 300,
            'revenue_account_id' => $badOverride->id,
        ]);

        $booking = $this->createBooking(['service_id' => $service->id]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500, 'paid_amount' => 500, 'pay_method' => 'cash',
        ]);

        $deptRevenue = Account::where('code', '4010')->firstOrFail();
        $entry = JournalEntry::where('reference', $booking->file_no)->sole();
        $this->assertSame($deptRevenue->id, $entry->credit_account_id);
    }

    public function test_completion_transition_after_full_incremental_payment_does_not_double_post(): void
    {
        $booking = $this->createBooking(['price' => 300]);

        // Full payment posted incrementally via PayBookingController
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 300, 'paid_amount' => 300, 'pay_method' => 'cash',
        ]);

        $this->assertEquals(300.00, JournalEntry::where('reference', $booking->file_no)->sum('amount'));

        // Simulate the completion-triggered re-post call site
        $booking->refresh();
        app(AutoPostBookingPaymentAction::class)->execute($booking);

        // Still only 300 posted in total — no double post
        $this->assertEquals(300.00, JournalEntry::where('reference', $booking->file_no)->sum('amount'));
        $this->assertSame(1, JournalEntry::where('reference', $booking->file_no)->count());
    }

    public function test_two_separate_installments_each_post_their_own_delta(): void
    {
        $booking = $this->createBooking(['price' => 500]);

        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500, 'paid_amount' => 200, 'pay_method' => 'cash',
        ]);
        $this->actingAs($this->user)->patch("/booking/{$booking->id}/pay", [
            'price' => 500, 'paid_amount' => 300, 'pay_method' => 'cash',
        ]);

        $this->assertEquals(500.00, JournalEntry::where('reference', $booking->file_no)->sum('amount'));
        $this->assertSame(2, JournalEntry::where('reference', $booking->file_no)->count());
    }
}
