<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryItemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_updates_item_details(): void
    {
        $item = InventoryItem::create(['name' => 'شاش طبي', 'code' => 'GZ-1', 'quantity' => 10, 'unit_cost' => 5, 'sell_price' => 8]);

        $response = $this->actingAs($this->user)->put("/inventory/{$item->id}", [
            'name' => 'شاش طبي معقم',
            'code' => 'GZ-1',
            'unit_cost' => 6,
            'sell_price' => 9,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertSame('شاش طبي معقم', $item->name);
        $this->assertEquals(6, $item->unit_cost);
        $this->assertEquals(9, $item->sell_price);
    }

    public function test_update_never_overwrites_quantity(): void
    {
        $item = InventoryItem::create(['name' => 'قفازات', 'quantity' => 42]);

        $this->actingAs($this->user)->put("/inventory/{$item->id}", [
            'name' => 'قفازات معقمة',
            'quantity' => 0,
        ]);

        $item->refresh();
        $this->assertEquals(42, $item->quantity);
    }

    public function test_requires_inventory_write_permission(): void
    {
        $item = InventoryItem::create(['name' => 'قفازات', 'quantity' => 10]);
        $viewer = User::factory()->create();
        $viewer->assignRole(Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']));

        $response = $this->actingAs($viewer)->put("/inventory/{$item->id}", ['name' => 'اسم آخر']);

        $response->assertForbidden();
    }
}
