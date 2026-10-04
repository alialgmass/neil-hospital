<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * PUT / DELETE /{surgery|lasik}/{id}/supplies/{index} — edit or remove one
 * already-added supply line.
 */
class SupplyLineEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->writer = $this->userWithPermissions(['surgery.write', 'lasik.write']);
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

    private function makeItem(string $name, float $quantity = 100): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => ItemCategory::Medical,
            'unit' => 'piece', 'quantity' => $quantity, 'min_quantity' => 1,
            'unit_cost' => 5, 'sell_price' => 10,
        ]);
    }

    private function makeSurgery(string $dept = 'surgery'): Surgery
    {
        $surgeon = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => $dept,
            'visit_date' => now()->toDateString(), 'price' => 6500, 'paid_amount' => 0, 'doctor_id' => $surgeon->id,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return Surgery::create([
            'booking_id' => $booking->id, 'surgeon_id' => $surgeon->id, 'dept' => $dept, 'status' => 'in_progress',
            'supplies_used' => [], 'supply_total' => 0,
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

    private function addItem(Surgery $surgery, InventoryItem $item, float $qty, float $unitCost = 10): void
    {
        $this->actingAs($this->writer)
            ->post("/{$surgery->dept->value}/{$surgery->id}/supplies", [
                'items' => [['inventory_item_id' => $item->id, 'name' => $item->name, 'qty' => $qty, 'unit_cost' => $unitCost]],
            ])
            ->assertSessionHasNoErrors();
    }

    private function addBundle(Surgery $surgery, SupplyBundle $bundle): void
    {
        $this->actingAs($this->writer)
            ->post("/{$surgery->dept->value}/{$surgery->id}/supplies", [
                'bundles' => [['bundle_id' => $bundle->id, 'qty' => 1]],
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_updates_quantity_and_price_of_an_item_line(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $gauze = $this->makeItem('شاش');
        $this->addItem($surgery, $gloves, 2);
        $this->addItem($surgery, $gauze, 1, 4);

        $this->actingAs($this->writer)
            ->put("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $gloves->id, 'qty' => 5, 'unit_cost' => 12])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $surgery->refresh();
        $this->assertEquals(5, $surgery->supplies_used[0]['qty']);
        $this->assertEquals(60, $surgery->supplies_used[0]['total']);
        $this->assertEquals(64.0, (float) $surgery->supply_total);
        // Individual items never touch stock.
        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
    }

    public function test_deletes_an_item_line_and_recomputes_total(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $gauze = $this->makeItem('شاش');
        $this->addItem($surgery, $gloves, 2);
        $this->addItem($surgery, $gauze, 1, 4);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(1, $surgery->supplies_used);
        $this->assertSame($gauze->id, $surgery->supplies_used[0]['inventory_item_id']);
        $this->assertEquals(4.0, (float) $surgery->supply_total);
    }

    public function test_deleting_a_bundle_returns_stock_reverses_entries_and_removes_permit(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $bundle = $this->makeBundle($gloves);
        $this->addBundle($surgery, $bundle);

        $this->assertEquals(98, (float) $gloves->fresh()->quantity);
        $this->assertNotEmpty($surgery->fresh()->supplies_used[0]['permit_id']);
        $postedEntries = JournalEntry::where('source', JournalSource::SUPPLIES_USED)->count();
        $this->assertSame(2, $postedEntries);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $bundle->id])
            ->assertSessionHasNoErrors();

        $surgery->refresh();
        $this->assertCount(0, $surgery->supplies_used);
        $this->assertEquals(0.0, (float) $surgery->supply_total);
        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
        $this->assertSame(0, StockPermit::count());
        $this->assertSame(0, JournalEntry::where('source', JournalSource::SUPPLIES_USED)->whereNull('reversed_at')->count());
        $this->assertSame(2, JournalEntry::where('source', JournalSource::REVERSAL)->count());
    }

    public function test_deleting_a_legacy_bundle_line_without_permit_id_finds_its_permit(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $bundle = $this->makeBundle($gloves);
        $this->addBundle($surgery, $bundle);

        $lines = $surgery->fresh()->supplies_used;
        unset($lines[0]['permit_id']);
        $surgery->update(['supplies_used' => $lines]);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $bundle->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
        $this->assertSame(0, StockPermit::count());
    }

    public function test_bundle_lines_cannot_be_edited_in_place(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $bundle = $this->makeBundle($gloves);
        $this->addBundle($surgery, $bundle);

        $this->actingAs($this->writer)
            ->put("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $bundle->id, 'qty' => 3, 'unit_cost' => 200])
            ->assertSessionHasErrors('line');

        $this->assertEquals(200.0, (float) $surgery->fresh()->supply_total);
    }

    public function test_stale_line_reference_is_rejected(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $gauze = $this->makeItem('شاش');
        $this->addItem($surgery, $gloves, 2);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $gauze->id])
            ->assertSessionHasErrors('line');

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/5", ['line_ref' => $gloves->id])
            ->assertSessionHasErrors('line');

        $this->assertCount(1, $surgery->fresh()->supplies_used);
    }

    public function test_works_for_lasik_cases_and_checks_department(): void
    {
        $lasikCase = $this->makeSurgery('lasik');
        $gloves = $this->makeItem('جوانتي');
        $this->addItem($lasikCase, $gloves, 2);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$lasikCase->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasErrors('line');

        $this->actingAs($this->writer)
            ->delete("/lasik/{$lasikCase->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $lasikCase->fresh()->supplies_used);
    }

    public function test_deleting_supply_after_payment_resyncs_doctor_dues(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $this->addItem($surgery, $gloves, 10, 10);

        $this->actingAs($this->userWithPermissions(['booking.pay']))
            ->patch("/booking/{$surgery->booking_id}/pay", ['paid_amount' => 6500, 'pay_method' => 'cash'])
            ->assertRedirect();

        $liveDues = fn () => (float) JournalEntry::where('source', JournalSource::DOCTOR_SHIFT->value)
            ->where('idempotency_key', 'like', 'doctor_dues:%')
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->sum('amount');

        $duesBefore = $liveDues();
        $this->assertGreaterThan(0, $duesBefore);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta($duesBefore + 100, $liveDues(), 0.01);
    }

    public function test_requires_write_permission(): void
    {
        $surgery = $this->makeSurgery();
        $gloves = $this->makeItem('جوانتي');
        $this->addItem($surgery, $gloves, 2);

        $this->actingAs($this->userWithPermissions(['surgery.view']))
            ->delete("/surgery/{$surgery->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertForbidden();

        $this->assertCount(1, $surgery->fresh()->supplies_used);
    }
}
