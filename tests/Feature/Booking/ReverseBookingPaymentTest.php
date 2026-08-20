<?php

namespace Tests\Feature\Booking;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostBookingPaymentAction;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Actions\ReverseBookingPaymentAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Tests\TestCase;

class ReverseBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function createBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'file_no' => 'MRN-'.uniqid(),
            'patient_name' => 'محمد علي',
            'dept' => 'clinic',
            'visit_date' => '2026-04-20',
            'price' => 300,
            'discount' => 0,
            'ins_amount' => 0,
            'paid_amount' => 300,
            'pay_method' => 'cash',
            'pay_status' => 'paid',
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_reverse_reverses_revenue_entry_with_original_amount(): void
    {
        $booking = $this->createBooking();
        app(AutoPostBookingPaymentAction::class)->execute($booking, 300);

        app(ReverseBookingPaymentAction::class)->execute($booking);

        $revenue = Account::where('code', '4010')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();

        $reversal = JournalEntry::where('source', 'reversal')->sole();
        $this->assertEquals(300.00, (float) $reversal->amount);
        $this->assertSame($revenue->id, $reversal->debit_account_id);
        $this->assertSame($cash->id, $reversal->credit_account_id);

        $original = JournalEntry::where('reference', $booking->file_no)->where('source', 'booking')->sole();
        $this->assertNotNull($original->reversed_at);
    }

    public function test_reverse_also_reverses_doctor_dues_entry(): void
    {
        $booking = $this->createBooking();
        app(AutoPostBookingPaymentAction::class)->execute($booking, 300);
        app(AutoPostDoctorDuesAction::class)->execute(
            dept: $booking->dept,
            amount: 100,
            doctorName: 'د. أحمد',
            reference: $booking->file_no,
        );

        app(ReverseBookingPaymentAction::class)->execute($booking);

        $duesReversal = JournalEntry::where('source', 'reversal')
            ->where('amount', 100)
            ->first();

        $this->assertNotNull($duesReversal, 'Doctor dues entry should also be reversed');

        $doctorPayable = Account::where('code', '2010')->firstOrFail();
        $this->assertEquals(0.00, (float) $doctorPayable->fresh()->balance);
    }

    public function test_reverse_is_idempotent_and_does_not_double_refund(): void
    {
        $booking = $this->createBooking();
        app(AutoPostBookingPaymentAction::class)->execute($booking, 300);

        app(ReverseBookingPaymentAction::class)->execute($booking);
        app(ReverseBookingPaymentAction::class)->execute($booking); // second cancel attempt

        $this->assertSame(1, JournalEntry::where('source', 'reversal')->count());
    }
}
