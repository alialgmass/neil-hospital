<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\TreasuryEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'reports.clinical', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('reports.clinical');

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_treasury_summary_only_reflects_the_selected_days_activity(): void
    {
        // Regression test: the daily report previously called treasuryBalance()
        // with no date filter at all, so its "treasury" figures were the
        // all-time cumulative totals and never changed no matter which date
        // was picked — defeating the purpose of a "daily" report.
        TreasuryEntry::create(['type' => 'in', 'description' => 'أمس', 'amount' => 1000, 'date' => '2026-06-19']);
        TreasuryEntry::create(['type' => 'in', 'description' => 'اليوم', 'amount' => 300, 'date' => '2026-06-20']);
        TreasuryEntry::create(['type' => 'out', 'description' => 'اليوم', 'amount' => 100, 'date' => '2026-06-20']);

        $response = $this->actingAs($this->user)->get('/reports/daily?date=2026-06-20');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('treasury.total_in', 300)
            ->where('treasury.total_out', 100)
            ->where('treasury.balance', 200));
    }

    public function test_treasury_summary_changes_when_a_different_date_is_selected(): void
    {
        TreasuryEntry::create(['type' => 'in', 'description' => 'يوم 1', 'amount' => 1000, 'date' => '2026-06-19']);
        TreasuryEntry::create(['type' => 'in', 'description' => 'يوم 2', 'amount' => 300, 'date' => '2026-06-20']);

        $day1 = $this->actingAs($this->user)->get('/reports/daily?date=2026-06-19');
        $day2 = $this->actingAs($this->user)->get('/reports/daily?date=2026-06-20');

        $day1->assertInertia(fn ($page) => $page->where('treasury.total_in', 1000));
        $day2->assertInertia(fn ($page) => $page->where('treasury.total_in', 300));
    }
}
