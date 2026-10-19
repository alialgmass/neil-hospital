<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StockTakePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        foreach (['inventory.view', 'inventory.write', 'stocktake.view', 'stocktake.adjust'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeItem(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'قطن طبي', 'code' => 'ITM-'.uniqid(), 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 10, 'min_quantity' => 0, 'unit_cost' => 5, 'sell_price' => 0,
        ]);
    }

    public function test_inventory_permissions_alone_no_longer_grant_stock_take(): void
    {
        $user = $this->userWith(['inventory.view', 'inventory.write']);
        $item = $this->makeItem();

        $this->actingAs($user)->get('/stock-take')->assertForbidden();
        $this->actingAs($user)
            ->post('/stock-take', ['counts' => [['item_id' => $item->id, 'physical_qty' => 8]]])
            ->assertForbidden();

        $this->assertSame(10.0, (float) $item->fresh()->quantity);
    }

    public function test_view_permission_allows_page_but_not_adjustment(): void
    {
        $user = $this->userWith(['stocktake.view']);
        $item = $this->makeItem();

        $this->actingAs($user)->get('/stock-take')->assertOk();
        $this->actingAs($user)
            ->post('/stock-take', ['counts' => [['item_id' => $item->id, 'physical_qty' => 8]]])
            ->assertForbidden();
    }

    public function test_adjust_permission_allows_stock_take_adjustment(): void
    {
        $user = $this->userWith(['stocktake.view', 'stocktake.adjust']);
        $item = $this->makeItem();

        $this->actingAs($user)
            ->post('/stock-take', ['counts' => [['item_id' => $item->id, 'physical_qty' => 8]]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(8.0, (float) $item->fresh()->quantity);
    }

    public function test_migration_grants_new_permissions_to_existing_inventory_holders(): void
    {
        Permission::whereIn('name', ['stocktake.view', 'stocktake.adjust'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $keeperRole = Role::create(['name' => 'keeper', 'guard_name' => 'web']);
        $keeperRole->givePermissionTo(['inventory.view', 'inventory.write']);
        $viewerRole = Role::create(['name' => 'inv-viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo(['inventory.view']);
        $directUser = User::factory()->create();
        $directUser->givePermissionTo('inventory.write');

        $migration = require database_path('migrations/2026_10_04_153928_add_stocktake_permissions.php');
        $migration->up();

        $this->assertTrue($keeperRole->fresh()->hasPermissionTo('stocktake.view'));
        $this->assertTrue($keeperRole->fresh()->hasPermissionTo('stocktake.adjust'));
        $this->assertTrue($viewerRole->fresh()->hasPermissionTo('stocktake.view'));
        $this->assertFalse($viewerRole->fresh()->hasPermissionTo('stocktake.adjust'));
        $this->assertTrue($directUser->fresh()->hasDirectPermission('stocktake.adjust'));
    }
}
