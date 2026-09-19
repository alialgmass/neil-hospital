<?php

namespace Tests\Feature\Doctor;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostDoctorPaymentAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Doctor\Actions\RecordDoctorPaymentAction;
use Modules\Doctor\Models\Doctor;
use Tests\TestCase;

class DoctorPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function makeDoctor(): Doctor
    {
        return Doctor::create(['name' => 'د. أحمد', 'fee_type' => 'percentage', 'fee_value' => 10, 'is_active' => true]);
    }

    public function test_cash_payment_credits_cash_account(): void
    {
        $doctor = $this->makeDoctor();

        app(RecordDoctorPaymentAction::class)->execute([
            'doctor_id' => $doctor->id, 'amount' => 500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        $payable = Account::where('code', '2010')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();

        $entry = JournalEntry::where('source', 'doctor_payment')->sole();
        $this->assertSame($payable->id, $entry->debit_account_id);
        $this->assertSame($cash->id, $entry->credit_account_id);
        $this->assertEquals(500.00, (float) $entry->amount);
    }

    public function test_transfer_payment_credits_bank_account(): void
    {
        $doctor = $this->makeDoctor();

        app(RecordDoctorPaymentAction::class)->execute([
            'doctor_id' => $doctor->id, 'amount' => 500, 'method' => 'transfer', 'paid_at' => now()->toDateString(),
        ]);

        $bank = Account::where('code', '1020')->firstOrFail();
        $entry = JournalEntry::where('source', 'doctor_payment')->sole();
        $this->assertSame($bank->id, $entry->credit_account_id);
    }

    public function test_duplicate_action_call_does_not_double_post(): void
    {
        $doctor = $this->makeDoctor();

        $payment = app(RecordDoctorPaymentAction::class)->execute([
            'doctor_id' => $doctor->id, 'amount' => 500, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        // Simulate a duplicate invocation of the low-level autopost action for the same payment record.
        app(AutoPostDoctorPaymentAction::class)->execute($payment, $doctor->name);

        $this->assertSame(1, JournalEntry::where('source', 'doctor_payment')->count());
    }
}
