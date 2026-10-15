<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryItemSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'surgery.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_finds_items_by_plain_arabic_substring(): void
    {
        InventoryItem::create(['name' => 'عدسة داخل العين', 'quantity' => 10]);

        $response = $this->actingAs($this->user)->getJson('/surgery/items/search?q='.urlencode('عد'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('عدسة داخل العين', $response->json('0.name'));
    }

    public function test_finds_items_by_single_arabic_letter(): void
    {
        InventoryItem::create(['name' => 'عدسة داخل العين', 'quantity' => 10]);
        InventoryItem::create(['name' => 'شاش طبي', 'quantity' => 10]);

        $response = $this->actingAs($this->user)->getJson('/surgery/items/search?q='.urlencode('ع'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('عدسة داخل العين', $response->json('0.name'));
    }

    public function test_matches_regardless_of_ta_marbuta_ha_variant(): void
    {
        InventoryItem::create(['name' => 'إبرة تخدير', 'quantity' => 5]);

        // Query uses "ه" where the stored name uses "ة" — should still match.
        $response = $this->actingAs($this->user)->getJson('/surgery/items/search?q='.urlencode('ابره'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_matches_regardless_of_alef_hamza_variant(): void
    {
        InventoryItem::create(['name' => 'أبرة تخدير', 'quantity' => 5]);

        // Query uses plain "ا" where the stored name starts with hamza "أ".
        $response = $this->actingAs($this->user)->getJson('/surgery/items/search?q='.urlencode('ابرة'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_excludes_out_of_stock_items(): void
    {
        InventoryItem::create(['name' => 'عدسة داخل العين', 'quantity' => 0]);

        $response = $this->actingAs($this->user)->getJson('/surgery/items/search?q='.urlencode('عد'));

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }
}
