<?php

namespace Tests\Feature\Reporting;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Reporting\Services\ReportingService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CostCenterProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    private function postEntry(string $debitCode, string $creditCode, float $amount, string $costCenter, string $date = '2026-06-15'): JournalEntry
    {
        return app(JournalService::class)->record([
            'date' => $date,
            'description' => 'test',
            'debit_account_id' => Account::where('code', $debitCode)->value('id'),
            'credit_account_id' => Account::where('code', $creditCode)->value('id'),
            'amount' => $amount,
            'source' => 'manual',
            'cost_center' => $costCenter,
        ]);
    }

    public function test_revenue_expense_and_profit_are_computed_per_cost_center(): void
    {
        // Clinic: 1000 revenue, 200 expense → profit 800.
        $this->postEntry('1010', '4010', 1000, 'CC-CLINIC');
        $this->postEntry('5110', '2010', 200, 'CC-CLINIC');

        // Surgery: 10000 revenue, 2360 expense → profit 7640.
        $this->postEntry('1010', '4030', 10000, 'CC-SURG');
        $this->postEntry('5120', '2010', 2360, 'CC-SURG');

        // Admin: no revenue, 500 expense → profit -500.
        $this->postEntry('5250', '1010', 500, 'CC-ADMIN');

        $result = app(ReportingService::class)->costCenterProfitability('2026-06-01', '2026-06-30');

        $rows = collect($result['rows'])->keyBy('cost_center');

        $this->assertEquals(1000.0, $rows['CC-CLINIC']['revenue']);
        $this->assertEquals(200.0, $rows['CC-CLINIC']['expense']);
        $this->assertEquals(800.0, $rows['CC-CLINIC']['profit']);

        $this->assertEquals(10000.0, $rows['CC-SURG']['revenue']);
        $this->assertEquals(2360.0, $rows['CC-SURG']['expense']);
        $this->assertEquals(7640.0, $rows['CC-SURG']['profit']);

        $this->assertEquals(0.0, $rows['CC-ADMIN']['revenue']);
        $this->assertEquals(500.0, $rows['CC-ADMIN']['expense']);
        $this->assertEquals(-500.0, $rows['CC-ADMIN']['profit']);

        $this->assertEquals(11000.0, $result['totalRevenue']);
        $this->assertEquals(3060.0, $result['totalExpense']);
        $this->assertEquals(7940.0, $result['netProfit']);
    }

    public function test_a_reversed_entry_nets_back_out_of_revenue_for_its_cost_center(): void
    {
        $entry = $this->postEntry('1031', '4130', 6500, 'CC-INS');

        app(JournalService::class)->reverse(
            entry: $entry,
            reversalSource: JournalSource::REVERSAL,
            reference: 'REV-test',
            date: '2026-06-16',
        );

        $result = app(ReportingService::class)->costCenterProfitability('2026-06-01', '2026-06-30');
        $rows = collect($result['rows'])->keyBy('cost_center');

        // Revenue and its reversal cancel out to zero, not double-counted.
        $this->assertEquals(0.0, $rows['CC-INS']['revenue'] ?? 0.0);
    }

    public function test_supports_a_date_range_narrower_than_all_posted_entries(): void
    {
        $this->postEntry('1010', '4010', 1000, 'CC-CLINIC', '2026-05-15');
        $this->postEntry('1010', '4010', 300, 'CC-CLINIC', '2026-06-15');

        $result = app(ReportingService::class)->costCenterProfitability('2026-06-01', '2026-06-30');
        $rows = collect($result['rows'])->keyBy('cost_center');

        $this->assertEquals(300.0, $rows['CC-CLINIC']['revenue']);
    }

    public function test_endpoint_returns_the_report_for_an_authorized_user(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'reports.financial', 'guard_name' => 'web']));
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->postEntry('1010', '4010', 500, 'CC-CLINIC');

        $response = $this->actingAs($user)->getJson('/reports/cost-center-profitability?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertJsonPath('totalRevenue', 500);
    }
}
