<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorEntitlement;
use Modules\Doctor\Models\DoctorPayment;
use Tests\TestCase;

class PurgeDataBeforeDateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(string $fileNo, string $visitDate): Booking
    {
        return Booking::create([
            'id' => (string) Str::ulid(),
            'file_no' => $fileNo,
            'patient_name' => 'Patient '.$fileNo,
            'dept' => 'clinic',
            'service_name' => 'Kashf',
            'price' => 100,
            'visit_date' => $visitDate,
            'pay_status' => 'unpaid',
            'status' => 'confirmed',
        ]);
    }

    private function makeJournal(string $date): JournalEntry
    {
        $debit = Account::firstOrCreate(['code' => '1010'], ['name' => 'Cash', 'group' => 'assets', 'nature' => 'debit']);
        $credit = Account::firstOrCreate(['code' => '4110'], ['name' => 'Revenue', 'group' => 'revenues', 'nature' => 'credit']);

        return JournalEntry::create([
            'date' => $date,
            'description' => 'Entry '.$date,
            'amount' => 100,
            'source' => JournalSource::BOOKING,
            'debit_account_id' => $debit->id,
            'credit_account_id' => $credit->id,
        ]);
    }

    public function test_deletes_only_records_before_cutoff(): void
    {
        $old = $this->makeBooking('F-OLD', '2026-09-30');
        $new = $this->makeBooking('F-NEW', '2026-10-01');

        $oldJournal = $this->makeJournal('2026-09-30');
        $newJournal = $this->makeJournal('2026-10-01');

        $oldTreasury = TreasuryEntry::create(['type' => 'in', 'description' => 'old', 'amount' => 10, 'date' => '2026-09-30', 'source' => 'manual']);
        $newTreasury = TreasuryEntry::create(['type' => 'in', 'description' => 'new', 'amount' => 10, 'date' => '2026-10-01', 'source' => 'manual']);

        $doctor = Doctor::create(['id' => (string) Str::ulid(), 'name' => 'Dr Test', 'specialty' => 'Eye']);

        $oldDue = DoctorEntitlement::create(['booking_id' => $old->id, 'doctor_id' => $doctor->id, 'amount' => 50, 'source' => 'contract', 'status' => 'pending']);
        $newDue = DoctorEntitlement::create(['booking_id' => $new->id, 'doctor_id' => $doctor->id, 'amount' => 50, 'source' => 'contract', 'status' => 'pending']);

        $oldPayment = DoctorPayment::create(['doctor_id' => $doctor->id, 'amount' => 20, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30', 'paid_at' => '2026-09-30']);
        $newPayment = DoctorPayment::create(['doctor_id' => $doctor->id, 'amount' => 20, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'paid_at' => '2026-10-01']);

        $this->artisan('data:purge-before', ['date' => '2026-10-01', '--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('bookings', ['id' => $old->id]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $oldJournal->id]);
        $this->assertDatabaseMissing('treasury_entries', ['id' => $oldTreasury->id]);
        $this->assertDatabaseMissing('doctor_entitlements', ['id' => $oldDue->id]);
        $this->assertDatabaseMissing('dr_payments', ['id' => $oldPayment->id]);

        $this->assertDatabaseHas('bookings', ['id' => $new->id]);
        $this->assertDatabaseHas('journal_entries', ['id' => $newJournal->id]);
        $this->assertDatabaseHas('treasury_entries', ['id' => $newTreasury->id]);
        $this->assertDatabaseHas('doctor_entitlements', ['id' => $newDue->id]);
        $this->assertDatabaseHas('dr_payments', ['id' => $newPayment->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $old = $this->makeBooking('F-OLD', '2026-09-30');
        $journal = $this->makeJournal('2026-09-30');

        $this->artisan('data:purge-before', ['date' => '2026-10-01', '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $old->id]);
        $this->assertDatabaseHas('journal_entries', ['id' => $journal->id]);
    }

    public function test_cancelled_confirmation_deletes_nothing(): void
    {
        $old = $this->makeBooking('F-OLD', '2026-09-30');

        $this->artisan('data:purge-before', ['date' => '2026-10-01'])
            ->expectsConfirmation('حذف نهائي لكل ما سبق قبل 2026-10-01؟ لا يمكن التراجع.', 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $old->id]);
    }

    public function test_resets_doctor_debt_only_when_requested(): void
    {
        $doctor = Doctor::create(['id' => (string) Str::ulid(), 'name' => 'Dr Debt', 'specialty' => 'Eye', 'doctor_debt_balance' => 75]);

        $this->artisan('data:purge-before', ['date' => '2026-10-01', '--force' => true])->assertSuccessful();
        $this->assertSame('75.00', $doctor->fresh()->doctor_debt_balance);

        $this->artisan('data:purge-before', ['date' => '2026-10-01', '--force' => true, '--reset-doctor-debt' => true])->assertSuccessful();
        $this->assertSame('0.00', $doctor->fresh()->doctor_debt_balance);
    }

    public function test_rejects_invalid_date(): void
    {
        $this->artisan('data:purge-before', ['date' => '1/10', '--force' => true])->assertFailed();
    }
}
