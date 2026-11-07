<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Models\TreasuryEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreasuryStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'treasury.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'treasury_viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('treasury.view');
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($role);
    }

    private function entry(string $type, float $amount, string $date): void
    {
        TreasuryEntry::create([
            'type' => $type,
            'description' => 'حركة',
            'amount' => $amount,
            'date' => $date,
            'source' => 'manual',
        ]);
    }

    public function test_statement_carries_opening_balance_and_running_balance(): void
    {
        $this->entry('in', 1000, '2026-09-30');
        $this->entry('in', 500, '2026-10-02');
        $this->entry('out', 200, '2026-10-03');

        $this->actingAs($this->viewer)
            ->get('/treasury/statement?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('treasury/Statement')
                ->where('statement.opening_balance', 1000)
                ->has('statement.statement', 2)
                ->where('statement.statement.0.balance', 1500)
                ->where('statement.statement.1.balance', 1300)
                ->where('statement.statement.1.out', 200));
    }

    public function test_statement_requires_treasury_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/treasury/statement')
            ->assertForbidden();
    }
}
