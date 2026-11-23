<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryItemActivationTest extends TestCase
{
    use RefreshDatabase;

    private User $storeKeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $permissions = ['inventory.view', 'inventory.write', 'item_sales.create', 'stock_permits.write', 'purchases.create'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'store_keeper', 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $this->storeKeeper = User::factory()->create();
        $this->storeKeeper->assignRole($role);
    }

    private function makeItem(string $name, bool $isActive = true): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 10, 'min_quantity' => 5, 'unit_cost' => 20, 'sell_price' => 30, 'is_active' => $isActive,
        ]);
    }

    public function test_new_items_are_active_by_default(): void
    {
        $item = InventoryItem::create(['name' => 'شاش']);

        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_item_can_be_deactivated_and_reactivated(): void
    {
        $item = $this->makeItem('قفازات');

        $this->actingAs($this->storeKeeper)
            ->patch("/inventory/{$item->id}/active", ['is_active' => false])
            ->assertRedirect();
        $this->assertFalse($item->fresh()->is_active);

        $this->actingAs($this->storeKeeper)
            ->patch("/inventory/{$item->id}/active", ['is_active' => true])
            ->assertRedirect();
        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_toggle_requires_inventory_write_permission(): void
    {
        $item = $this->makeItem('قفازات');
        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo('inventory.view');
        $viewer = User::factory()->create();
        $viewer->assignRole($viewerRole);

        $this->actingAs($viewer)
            ->patch("/inventory/{$item->id}/active", ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_inventory_list_filters_by_status(): void
    {
        $this->makeItem('نشط');
        $this->makeItem('موقوف', false);

        $this->actingAs($this->storeKeeper)
            ->get('/inventory?status=inactive')
            ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'موقوف'));

        $this->actingAs($this->storeKeeper)
            ->get('/inventory')
            ->assertInertia(fn ($page) => $page->has('items.data', 2));
    }

    public function test_inactive_item_is_excluded_from_low_stock_count(): void
    {
        $item = $this->makeItem('منخفض');
        $item->update(['quantity' => 1]);
        $this->makeItem('منخفض موقوف', false)->update(['quantity' => 1]);

        $this->actingAs($this->storeKeeper)
            ->get('/inventory')
            ->assertInertia(fn ($page) => $page->where('lowStockCount', 1));
    }

    public function test_inactive_item_cannot_be_sold(): void
    {
        $item = $this->makeItem('موقوف', false);

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', [
                'invoice_date' => '2026-10-04',
                'customer_name' => 'محمد علي',
                'pay_method' => 'cash',
                'discount' => 0,
                'items' => [['item_id' => $item->id, 'qty' => 1, 'unit_price' => 30]],
            ])
            ->assertSessionHasErrors('items.0.item_id');

        $this->assertEquals(10, $item->fresh()->quantity);
    }

    public function test_inactive_item_cannot_be_issued(): void
    {
        $item = $this->makeItem('موقوف', false);

        $this->actingAs($this->storeKeeper)
            ->post('/stock-permits/issue', [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 1]],
            ])
            ->assertSessionHasErrors('items.0.item_id');

        $this->assertEquals(10, $item->fresh()->quantity);
    }

    public function test_inactive_item_is_hidden_from_item_search_and_sale_selector(): void
    {
        $this->makeItem('شاش فعال');
        $this->makeItem('شاش موقوف', false);

        $this->actingAs($this->storeKeeper)
            ->getJson('/purchases/items/search?q=شاش')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'شاش فعال');

        $this->actingAs($this->storeKeeper)
            ->get('/item-sales')
            ->assertInertia(fn ($page) => $page->has('selectableItems', 1)->where('selectableItems.0.name', 'شاش فعال'));
    }
}
