<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostStockIssueAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Tests\TestCase;

class AutoPostStockIssueActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Account::create(['code' => '1050', 'name' => 'المخزون', 'group' => 'assets', 'nature' => 'debit']);
        Account::create(['code' => '5010', 'name' => 'تكلفة مستلزمات طبية', 'group' => 'expenses', 'nature' => 'debit']);
        Account::create(['code' => '5250', 'name' => 'مصروفات إدارية وتسويقية', 'group' => 'expenses', 'nature' => 'debit']);
        Account::create(['code' => '5240', 'name' => 'مصروفات الصيانة', 'group' => 'expenses', 'nature' => 'debit']);
    }

    private function makeItem(?ItemCategory $category): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'مادة تجريبية',
            'code' => 'ITM-'.uniqid(),
            'category' => $category,
            'unit' => 'piece',
            'quantity' => 100,
            'min_quantity' => 1,
            'unit_cost' => 10,
            'sell_price' => 15,
        ]);
    }

    private function makePermit(InventoryItem $item, float $qty = 2): StockPermit
    {
        $permit = StockPermit::create([
            'permit_no' => 'OUT-TEST-'.uniqid(),
            'type' => 'out',
            'created_by' => auth()->id(),
        ]);

        $permit->items()->create([
            'item_id' => $item->id,
            'item_name' => $item->name,
            'qty' => $qty,
            'unit_cost' => $item->unit_cost,
        ]);

        return $permit->load('items');
    }

    public function test_posts_to_office_expense_account_for_office_category_item(): void
    {
        $item = $this->makeItem(ItemCategory::Office);
        $permit = $this->makePermit($item);

        app(AutoPostStockIssueAction::class)->execute($permit);

        $entry = JournalEntry::sole();
        $this->assertSame('5250', Account::find($entry->debit_account_id)->code);
    }

    public function test_posts_to_default_medical_expense_account_when_category_is_null(): void
    {
        $item = $this->makeItem(null);
        $permit = $this->makePermit($item);

        app(AutoPostStockIssueAction::class)->execute($permit);

        $entry = JournalEntry::sole();
        $this->assertSame('5010', Account::find($entry->debit_account_id)->code);
    }
}
