<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Actions\RecordSuppliesUsedAction;
use Modules\Surgery\Models\Surgery;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * POST /{surgery|lasik}/{id}/supplies — bulk add of individual items and
 * bundles in one atomic request.
 */
class RecordSuppliesBulkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeItem(string $name, float $sellPrice = 10, float $quantity = 100): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => ItemCategory::Medical,
            'unit' => 'piece', 'quantity' => $quantity, 'min_quantity' => 1,
            'unit_cost' => $sellPrice / 2, 'sell_price' => $sellPrice,
        ]);
    }

    private function makeSurgery(string $dept = 'surgery', array $existingSupplies = []): Surgery
    {
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => $dept,
            'visit_date' => now()->toDateString(), 'price' => 6500, 'paid_amount' => 0,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return Surgery::create([
            'booking_id' => $booking->id, 'dept' => $dept, 'status' => 'in_progress',
            'supplies_used' => $existingSupplies,
            'supply_total' => array_sum(array_column($existingSupplies, 'total')),
        ]);
    }

    private function makeBundle(InventoryItem $item): SupplyBundle
    {
        $bundle = SupplyBundle::create(['name' => 'حزمة', 'code' => 'B-'.uniqid(), 'price' => 200, 'is_active' => true]);
        $bundle->items()->create([
            'inventory_item_id' => $item->id, 'item_name' => $item->name, 'qty' => 2, 'unit_cost' => $item->unit_cost,
        ]);

        return $bundle;
    }

    /**
     * @return array{inventory_item_id: string, name: string, qty: float|int, unit_cost: float|int}
     */
    private function line(InventoryItem $item, float|int $qty, float|int|null $unitCost = null): array
    {
        return [
            'inventory_item_id' => $item->id,
            'name' => $item->name,
            'qty' => $qty,
            'unit_cost' => $unitCost ?? $item->sell_price,
        ];
    }

    public function test_adds_a_single_supply(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", ['items' => [$this->line($gloves, 10)]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(1, $surgery->supplies_used);
        $this->assertEquals(10, $surgery->supplies_used[0]['qty']);
        $this->assertEquals(50.0, (float) $surgery->supply_total);
    }

    public function test_adds_many_supplies_in_one_request_and_appends_to_existing_lines(): void
    {
        $surgery = $this->makeSurgery('surgery', [
            ['inventory_item_id' => 'old', 'name' => 'قديم', 'qty' => 1, 'unit_cost' => 30, 'total' => 30, 'is_bundle' => false],
        ]);
        $gloves = $this->makeItem('جوانتي', 5);
        $syringe = $this->makeItem('سرنجة', 3);
        $cotton = $this->makeItem('قطن', 2);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'surgery_id' => $surgery->id,
                'items' => [$this->line($gloves, 10), $this->line($syringe, 5), $this->line($cotton, 20)],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(4, $surgery->supplies_used);
        $this->assertSame([1, 10, 5, 20], array_map(fn ($l) => (int) $l['qty'], $surgery->supplies_used));
        // 30 + 10×5 + 5×3 + 20×2
        $this->assertEquals(135.0, (float) $surgery->supply_total);
    }

    public function test_line_total_is_computed_server_side_ignoring_client_total(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", ['items' => [array_merge($this->line($gloves, 4), ['total' => 1])]])
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertEquals(20.0, $surgery->supplies_used[0]['total']);
        $this->assertEquals(20.0, (float) $surgery->supply_total);
    }

    public function test_duplicate_items_in_one_request_are_merged_by_summing_quantity(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);
        $cotton = $this->makeItem('قطن', 2);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'items' => [$this->line($gloves, 3), $this->line($cotton, 1), $this->line($gloves, 7)],
            ])
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(2, $surgery->supplies_used);
        $this->assertSame($gloves->id, $surgery->supplies_used[0]['inventory_item_id']);
        $this->assertEquals(10, $surgery->supplies_used[0]['qty']);
        $this->assertEquals(50.0, $surgery->supplies_used[0]['total']);
        $this->assertEquals(52.0, (float) $surgery->supply_total);
    }

    public function test_duplicate_items_with_different_prices_are_rejected(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'items' => [$this->line($gloves, 3, 5), $this->line($gloves, 2, 6)],
            ])
            ->assertSessionHasErrors('items.1.unit_cost');

        $this->assertEmpty($surgery->fresh()->supplies_used);
    }

    public function test_validation_errors_for_items_and_quantities_save_nothing(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'items' => [
                    $this->line($gloves, 2),
                    ['inventory_item_id' => 'does-not-exist', 'name' => 'x', 'qty' => 1, 'unit_cost' => 1],
                    $this->line($gloves, 0),
                    array_merge($this->line($gloves, 1), ['unit_cost' => -3]),
                ],
            ])
            ->assertSessionHasErrors(['items.1.inventory_item_id', 'items.2.qty', 'items.3.unit_cost']);

        $this->assertEmpty($surgery->fresh()->supplies_used);
    }

    public function test_empty_submission_is_rejected(): void
    {
        $surgery = $this->makeSurgery();

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", ['items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_body_surgery_id_must_match_the_url(): void
    {
        $surgery = $this->makeSurgery();
        $other = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", ['surgery_id' => $other->id, 'items' => [$this->line($gloves, 1)]])
            ->assertSessionHasErrors('surgery_id');

        $this->assertEmpty($surgery->fresh()->supplies_used);
        $this->assertEmpty($other->fresh()->supplies_used);
    }

    public function test_failure_rolls_back_bundle_stock_permits_and_journal_entries(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5, 100);
        $bundle = $this->makeBundle($gloves);

        $this->mock(RecordSuppliesUsedAction::class)
            ->shouldReceive('execute')
            ->andThrow(new RuntimeException('forced failure'));

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'items' => [$this->line($gloves, 1)],
                'bundles' => [['bundle_id' => $bundle->id, 'qty' => 1]],
            ])
            ->assertServerError();

        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
        $this->assertSame(0, StockPermit::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertEmpty($surgery->fresh()->supplies_used);
    }

    public function test_bundles_and_items_are_saved_together(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5, 100);
        $bundle = $this->makeBundle($gloves);

        $this->actingAs($this->userWithPermissions(['surgery.write']))
            ->post("/surgery/{$surgery->id}/supplies", [
                'items' => [$this->line($gloves, 2)],
                'bundles' => [['bundle_id' => $bundle->id, 'qty' => 1]],
            ])
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(2, $surgery->supplies_used);
        $this->assertTrue($surgery->supplies_used[1]['is_bundle']);
        // item 2×5 + bundle 200
        $this->assertEquals(210.0, (float) $surgery->supply_total);
        // Existing bundle behaviour is unchanged: bundle items are deducted from stock.
        $this->assertEquals(98, (float) $gloves->fresh()->quantity);
    }

    public function test_user_without_write_permission_cannot_add_supplies(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['surgery.view']))
            ->post("/surgery/{$surgery->id}/supplies", ['items' => [$this->line($gloves, 1)]])
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions(['surgery.view']))
            ->postJson("/surgery/{$surgery->id}/supplies", ['items' => [$this->line($gloves, 1)]])
            ->assertStatus(403);

        $this->assertEmpty($surgery->fresh()->supplies_used);
    }

    public function test_lasik_write_is_required_for_lasik_and_does_not_grant_surgery(): void
    {
        $lasikCase = $this->makeSurgery('lasik');
        $surgeryCase = $this->makeSurgery('surgery');
        $gloves = $this->makeItem('جوانتي', 5);
        $lasikUser = $this->userWithPermissions(['lasik.write']);

        $this->actingAs($lasikUser)
            ->post("/lasik/{$lasikCase->id}/supplies", ['items' => [$this->line($gloves, 1)]])
            ->assertSessionHasNoErrors();
        $this->assertCount(1, $lasikCase->fresh()->supplies_used);

        $this->actingAs($lasikUser)
            ->post("/surgery/{$surgeryCase->id}/supplies", ['items' => [$this->line($gloves, 1)]])
            ->assertForbidden();
    }

    public function test_case_from_another_department_is_rejected(): void
    {
        $surgeryCase = $this->makeSurgery('surgery');
        $gloves = $this->makeItem('جوانتي', 5);

        $this->actingAs($this->userWithPermissions(['lasik.write']))
            ->post("/lasik/{$surgeryCase->id}/supplies", ['items' => [$this->line($gloves, 1)]])
            ->assertSessionHasErrors('surgery_id');

        $this->assertEmpty($surgeryCase->fresh()->supplies_used);
    }
}
