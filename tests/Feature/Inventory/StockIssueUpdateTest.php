<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Actions\IssueStockPermitAction;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockIssueUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $storeKeeper;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        Permission::firstOrCreate(['name' => 'inventory.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);

        $storeKeeperRole = Role::firstOrCreate(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperRole->givePermissionTo(['inventory.view', 'inventory.write']);
        $this->storeKeeper = User::factory()->create();
        $this->storeKeeper->assignRole($storeKeeperRole);

        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo(['inventory.view']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($viewerRole);
    }

    private function makeItem(string $name, float $quantity = 10, float $unitCost = 5): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => 'medical', 'unit' => 'piece',
            'quantity' => $quantity, 'min_quantity' => 0, 'unit_cost' => $unitCost, 'sell_price' => 0,
        ]);
    }

    private function issuePermit(InventoryItem $item, float $qty): StockPermit
    {
        $this->actingAs($this->storeKeeper);

        return app(IssueStockPermitAction::class)->execute(
            ['department' => 'surgery', 'reason' => 'استهلاك', 'notes' => null],
            [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => $qty, 'unit_cost' => (float) $item->unit_cost]],
        );
    }

    private function activeSuppliesUsedTotal(): float
    {
        return (float) JournalEntry::where('source', JournalSource::SUPPLIES_USED)
            ->whereNull('reversed_at')
            ->sum('amount');
    }

    public function test_updating_quantity_adjusts_stock_and_reposts_journal(): void
    {
        $item = $this->makeItem('قطن طبي');
        $permit = $this->issuePermit($item, 4);

        $this->assertSame(6.0, (float) $item->fresh()->quantity);
        $this->assertSame(20.0, $this->activeSuppliesUsedTotal());

        $this->actingAs($this->storeKeeper)
            ->put("/stock-issue/{$permit->id}", [
                'department' => 'clinic',
                'reason' => 'تعديل',
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 7, 'unit_cost' => 5]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $permit->refresh()->load('items');

        $this->assertSame(3.0, (float) $item->fresh()->quantity);
        $this->assertSame('clinic', $permit->department->value);
        $this->assertSame('تعديل', $permit->reason);
        $this->assertCount(1, $permit->items);
        $this->assertSame(7.0, (float) $permit->items->first()->qty);
        $this->assertSame(35.0, $this->activeSuppliesUsedTotal());
        $this->assertSame(1, JournalEntry::where('source', JournalSource::REVERSAL)->count());
    }

    public function test_replacing_item_returns_old_item_to_stock(): void
    {
        $cotton = $this->makeItem('قطن طبي');
        $gauze = $this->makeItem('شاش', 10, 2);
        $permit = $this->issuePermit($cotton, 4);

        $this->actingAs($this->storeKeeper)
            ->put("/stock-issue/{$permit->id}", [
                'items' => [['item_id' => $gauze->id, 'item_name' => $gauze->name, 'qty' => 3, 'unit_cost' => 2]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(10.0, (float) $cotton->fresh()->quantity);
        $this->assertSame(7.0, (float) $gauze->fresh()->quantity);
        $this->assertSame(6.0, $this->activeSuppliesUsedTotal());
    }

    public function test_quantity_may_use_stock_released_by_the_same_permit(): void
    {
        $item = $this->makeItem('قطن طبي', 5);
        $permit = $this->issuePermit($item, 5);

        $this->actingAs($this->storeKeeper)
            ->put("/stock-issue/{$permit->id}", [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 5, 'unit_cost' => 5]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $item->fresh()->quantity);
    }

    public function test_quantity_above_available_stock_is_rejected_and_nothing_changes(): void
    {
        $item = $this->makeItem('قطن طبي');
        $permit = $this->issuePermit($item, 4);

        $this->actingAs($this->storeKeeper)
            ->put("/stock-issue/{$permit->id}", [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 11, 'unit_cost' => 5]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(6.0, (float) $item->fresh()->quantity);
        $this->assertSame(4.0, (float) $permit->items()->first()->qty);
        $this->assertSame(20.0, $this->activeSuppliesUsedTotal());
        $this->assertSame(0, JournalEntry::where('source', JournalSource::REVERSAL)->count());
    }

    public function test_user_without_write_permission_cannot_update(): void
    {
        $item = $this->makeItem('قطن طبي');
        $permit = $this->issuePermit($item, 4);

        $this->actingAs($this->viewer)
            ->put("/stock-issue/{$permit->id}", [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 1, 'unit_cost' => 5]],
            ])
            ->assertForbidden();

        $this->assertSame(6.0, (float) $item->fresh()->quantity);
    }

    public function test_update_requires_at_least_one_item(): void
    {
        $item = $this->makeItem('قطن طبي');
        $permit = $this->issuePermit($item, 4);

        $this->actingAs($this->storeKeeper)
            ->put("/stock-issue/{$permit->id}", ['items' => []])
            ->assertSessionHasErrors('items');

        $this->assertSame(6.0, (float) $item->fresh()->quantity);
    }
}
