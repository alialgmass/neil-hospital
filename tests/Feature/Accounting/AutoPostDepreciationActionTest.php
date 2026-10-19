<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostDepreciationAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Models\JournalEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutoPostDepreciationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public static function assetClassProvider(): array
    {
        return [
            'buildings' => ['buildings', '5261', '1121'],
            'medical_equipment' => ['medical_equipment', '5262', '1131'],
            'furniture' => ['furniture', '5263', '1141'],
            'computers' => ['computers', '5264', '1151'],
            'vehicles' => ['vehicles', '5265', '1161'],
        ];
    }

    #[DataProvider('assetClassProvider')]
    public function test_each_asset_class_posts_to_its_own_expense_and_accumulated_depreciation_pair(string $class, string $expenseCode, string $accumulatedCode): void
    {
        $asset = FixedAsset::create([
            'name' => 'أصل تجريبي', 'asset_class' => $class,
            'cost' => 12000, 'useful_life_months' => 120, 'acquired_at' => now()->subYear(),
        ]);

        $posted = app(AutoPostDepreciationAction::class)->execute('2026-09');

        $this->assertSame(1, $posted);

        $expense = Account::where('code', $expenseCode)->firstOrFail();
        $accumulated = Account::where('code', $accumulatedCode)->firstOrFail();

        $entry = JournalEntry::where('reference', $asset->id)->sole();
        $this->assertSame($expense->id, $entry->debit_account_id);
        $this->assertSame($accumulated->id, $entry->credit_account_id);
        $this->assertEquals(100.0, (float) $entry->amount); // 12000 / 120 months

        $this->assertEquals(100.0, (float) $asset->fresh()->accumulated_depreciation);
    }

    public function test_running_the_same_month_twice_does_not_duplicate_the_entry(): void
    {
        FixedAsset::create([
            'name' => 'جهاز ليزك', 'asset_class' => 'medical_equipment',
            'cost' => 60000, 'useful_life_months' => 60, 'acquired_at' => now()->subMonths(3),
        ]);

        app(AutoPostDepreciationAction::class)->execute('2026-09');
        $secondRunPosted = app(AutoPostDepreciationAction::class)->execute('2026-09');

        $this->assertSame(0, $secondRunPosted);
        $this->assertSame(1, JournalEntry::where('source', 'expense')->count());

        $asset = FixedAsset::sole();
        $this->assertEquals(1000.0, (float) $asset->accumulated_depreciation); // not doubled
    }

    public function test_depreciation_never_exceeds_the_asset_cost_and_never_touches_it(): void
    {
        $asset = FixedAsset::create([
            'name' => 'جهاز شبه مستهلك', 'asset_class' => 'computers',
            'cost' => 1000, 'useful_life_months' => 24,
            'accumulated_depreciation' => 980, 'acquired_at' => now()->subYears(2),
        ]);

        app(AutoPostDepreciationAction::class)->execute('2026-09');

        $asset->refresh();
        $this->assertEquals(1000.0, (float) $asset->accumulated_depreciation); // capped, not 980 + 41.67
        $this->assertEquals(1000.0, (float) $asset->cost); // original cost untouched

        // Next month: fully depreciated, no further postings.
        $posted = app(AutoPostDepreciationAction::class)->execute('2026-10');
        $this->assertSame(0, $posted);
    }
}
