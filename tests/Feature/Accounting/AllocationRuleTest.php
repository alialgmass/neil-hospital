<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AllocationRule;
use Modules\Accounting\Models\AllocationRuleTarget;
use Tests\TestCase;

/**
 * Scaffold only: no percentages are seeded, and no AutoPost action reads
 * from these tables yet — this just proves the structure holds together
 * and can't be pushed past 100% across a rule's targets.
 */
class AllocationRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public function test_no_percentages_are_seeded_by_default(): void
    {
        $this->assertSame(0, AllocationRule::count());
        $this->assertSame(0, AllocationRuleTarget::count());
    }

    public function test_a_rule_can_split_a_shared_expense_across_cost_centers(): void
    {
        $rent = Account::where('code', '5220')->firstOrFail();
        $rule = AllocationRule::create(['name' => 'إيجار المبنى الرئيسي', 'source_account_id' => $rent->id]);

        $rule->targets()->create(['cost_center' => 'CC-CLINIC', 'percentage' => 40]);
        $rule->targets()->create(['cost_center' => 'CC-SURG', 'percentage' => 35]);
        $rule->targets()->create(['cost_center' => 'CC-ADMIN', 'percentage' => 25]);

        $this->assertEquals(100.0, $rule->totalPercentage());
        $this->assertTrue($rule->isFullyAllocated());
    }

    public function test_targets_cannot_total_more_than_100_percent(): void
    {
        $rent = Account::where('code', '5220')->firstOrFail();
        $rule = AllocationRule::create(['name' => 'إيجار', 'source_account_id' => $rent->id]);

        $rule->targets()->create(['cost_center' => 'CC-CLINIC', 'percentage' => 70]);

        $this->expectException(AccountingException::class);
        $rule->targets()->create(['cost_center' => 'CC-SURG', 'percentage' => 40]);
    }
}
