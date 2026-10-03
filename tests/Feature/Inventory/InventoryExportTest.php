<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'inventory.view']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('inventory.view');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_export_route_returns_xlsx_download(): void
    {
        InventoryItem::create([
            'name' => 'قفازات',
            'category' => 'medical',
            'unit' => 'box',
            'quantity' => 10,
            'min_quantity' => 1,
            'unit_cost' => 50,
            'sell_price' => 80,
        ]);

        $response = $this->actingAs($this->user)->get('/inventory/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('inventory.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_export_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/inventory/export')->assertForbidden();
    }
}
