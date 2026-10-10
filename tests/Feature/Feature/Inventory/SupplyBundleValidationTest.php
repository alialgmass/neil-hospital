<?php

namespace Tests\Feature\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\SupplyBundle;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplyBundleValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('inventory.write');
    }

    private function payload(array $items): array
    {
        return ['name' => 'بند تجريبي', 'price' => 100, 'items' => $items];
    }

    public function test_bundle_without_items_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('supply-bundles.store'), $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, SupplyBundle::count());
    }

    public function test_bundle_item_not_linked_to_inventory_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('supply-bundles.store'), $this->payload([
                ['inventory_item_id' => '', 'item_name' => 'حر', 'qty' => 1, 'unit_cost' => 5],
            ]))
            ->assertSessionHasErrors('items.0.inventory_item_id');

        $this->assertSame(0, SupplyBundle::count());
    }

    public function test_bundle_with_linked_inventory_item_is_saved(): void
    {
        $item = InventoryItem::create(['name' => 'خيط', 'quantity' => 10]);

        $this->actingAs($this->user)
            ->post(route('supply-bundles.store'), $this->payload([
                ['inventory_item_id' => $item->id, 'item_name' => 'خيط', 'qty' => 2, 'unit_cost' => 5],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SupplyBundle::count());
    }
}
