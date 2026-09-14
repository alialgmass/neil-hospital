<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Database\Seeders\Historical\HistoricalClinicBookingsSeeder;
use Database\Seeders\Historical\HistoricalDoctorsSeeder;
use Database\Seeders\Historical\HistoricalInsuranceSeeder;
use Database\Seeders\Historical\HistoricalSurgeryBookingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Inventory\Models\Service;
use Tests\TestCase;

/**
 * The historical seeders post their accounting entirely through the AutoPost
 * actions, so they track whatever chart of accounts AccountCode currently
 * describes. This guards that they still run clean against الدليل المحاسبي v2.0
 * and that the ledger they produce is internally consistent.
 */
class HistoricalSeederAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
        $this->seed(AccountsSeeder::class);
        $this->seed(HistoricalDoctorsSeeder::class);
        $this->seed(HistoricalInsuranceSeeder::class);
        $this->seed(HistoricalSurgeryBookingsSeeder::class);
        $this->seed(HistoricalClinicBookingsSeeder::class);
    }

    public function test_historical_seeding_produces_a_balanced_ledger_on_the_v2_chart(): void
    {
        $this->assertGreaterThan(0, Booking::count());
        $this->assertGreaterThan(0, JournalEntry::count());

        $debitBalances = (float) DB::table('accounts')->where('nature', 'debit')->sum('balance');
        $creditBalances = (float) DB::table('accounts')->where('nature', 'credit')->sum('balance');

        $this->assertEqualsWithDelta($debitBalances, $creditBalances, 0.01, 'historical ledger does not foot');
    }

    public function test_no_historical_entry_posts_to_a_non_postable_account(): void
    {
        $nonPostableIds = Account::where('is_postable', false)->pluck('id');

        $this->assertSame(
            0,
            JournalEntry::whereIn('debit_account_id', $nonPostableIds)
                ->orWhereIn('credit_account_id', $nonPostableIds)
                ->count(),
        );
    }

    public function test_every_historical_booking_links_a_doctor_and_a_service(): void
    {
        // Every booking row in the sheets carries a doctor and a service name;
        // the seeder must resolve or create both, never leave them dangling.
        $this->assertSame(0, Booking::whereNull('service_id')->count());
        $this->assertGreaterThan(0, Service::whereIn('dept', ['clinic', 'surgery', 'labs'])->count());

        // Rows whose "doctor" column names a real person (not a payer/centre)
        // must be linked; at minimum the vast majority resolve.
        $withDoctor = Booking::whereNotNull('doctor_id')->count();
        $this->assertGreaterThan(Booking::count() * 0.8, $withDoctor);

        Booking::whereNotNull('doctor_id')->get()->each(
            fn (Booking $b) => $this->assertDatabaseHas('doctors', ['id' => $b->doctor_id]),
        );
    }

    public function test_revenue_lands_on_the_v2_department_accounts(): void
    {
        $clinicRevenue = Account::where('code', '4010')->first();
        $surgeryRevenue = Account::where('code', '4030')->first();
        $insuranceReceivable = Account::where('code', '1030')->first();

        $this->assertGreaterThan(0, JournalEntry::where('credit_account_id', $clinicRevenue->id)->sum('amount'));
        $this->assertGreaterThan(0, JournalEntry::where('credit_account_id', $surgeryRevenue->id)->sum('amount'));
        $this->assertGreaterThan(0, JournalEntry::where('debit_account_id', $insuranceReceivable->id)->sum('amount'));
    }
}
