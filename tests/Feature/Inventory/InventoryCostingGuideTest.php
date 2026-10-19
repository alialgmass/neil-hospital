<?php

namespace Tests\Feature\Inventory;

use App\Enums\Department;
use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostStockIssueAction;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Actions\StockTakeAdjustmentAction;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Inventory never leaves the books without its cost (guide §1.5 / appendix
 * §2): consumption is costed to the consuming department's account, count
 * differences are posted, and a relief of 1051 against anything that is
 * not a cost (or a supplier return) is refused.
 */
class InventoryCostingGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, array{0: Department, 1: string}> */
    public static function departmentCostAccounts(): array
    {
        return [
            'surgery' => [Department::Surgery, '5010'],
            'lasik' => [Department::Lasik, '5020'],
            'laser' => [Department::Laser, '5030'],
            'labs' => [Department::Labs, '5040'],
            'pentacam' => [Department::Pentacam, '5040'],
        ];
    }

    #[DataProvider('departmentCostAccounts')]
    public function test_medical_issue_is_costed_to_the_consuming_departments_account(Department $dept, string $costCode): void
    {
        $item = $this->makeItem(ItemCategory::Medical, 100, 10);
        $permit = $this->issue($item, 3, $dept);

        app(AutoPostStockIssueAction::class)->execute($permit);

        $entry = JournalEntry::sole();
        $this->assertSame($costCode, Account::find($entry->debit_account_id)->code);
        $this->assertSame('1051', Account::find($entry->credit_account_id)->code);
        $this->assertEquals(30.0, (float) $entry->amount);
    }

    public function test_stock_take_shortage_and_surplus_are_posted_at_cost(): void
    {
        $short = $this->makeItem(ItemCategory::Medical, 10, 50);
        $over = $this->makeItem(ItemCategory::Medical, 10, 30);

        app(StockTakeAdjustmentAction::class)->execute([
            ['item_id' => $short->id, 'physical_qty' => 0],   // 10 × 50 = 500 shortage (guide's example)
            ['item_id' => $over->id, 'physical_qty' => 20],   // 10 × 30 = 300 surplus
        ]);

        $this->assertEquals(500.0, (float) Account::where('code', '5010')->value('balance'));
        $this->assertEquals(300.0, (float) Account::where('code', '4220')->value('balance'));
        $this->assertEquals(-200.0, (float) Account::where('code', '1051')->value('balance'));

        $check = app(GuideConformanceService::class)->run()['inventory'];
        $this->assertTrue($check['passed'], implode("\n", $check['details']));
    }

    public function test_relieving_inventory_against_a_non_cost_account_is_refused(): void
    {
        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-10',
            'description' => 'x',
            'debit_account_id' => Account::where('code', '2201')->value('id'),
            'credit_account_id' => Account::where('code', '1051')->value('id'),
            'amount' => 100,
        ]);
    }

    private function makeItem(ItemCategory $category, float $quantity, float $unitCost): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'مادة '.uniqid(), 'code' => 'ITM-'.uniqid(), 'category' => $category, 'unit' => 'piece',
            'quantity' => $quantity, 'min_quantity' => 0, 'unit_cost' => $unitCost, 'sell_price' => $unitCost,
        ]);
    }

    private function issue(InventoryItem $item, float $qty, Department $dept): StockPermit
    {
        $permit = StockPermit::create(['permit_no' => 'OUT-'.uniqid(), 'type' => 'out', 'department' => $dept, 'created_by' => auth()->id()]);
        $permit->items()->create(['item_id' => $item->id, 'item_name' => $item->name, 'qty' => $qty, 'unit_cost' => $item->unit_cost]);

        return $permit->load('items');
    }
}
