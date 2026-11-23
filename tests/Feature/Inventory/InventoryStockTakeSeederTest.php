<?php

namespace Tests\Feature\Inventory;

use Database\Seeders\AccountsSeeder;
use Database\Seeders\InventoryStockTakeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\ItemUnit;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Tests\TestCase;

class InventoryStockTakeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public function test_lens_rows_match_existing_items_by_type_and_power_and_include_the_operating_room_count(): void
    {
        $softLens = $this->makeItem('عدسه لينه 20', unitCost: 350);
        $hardLens = $this->makeItem('عدسة صلبة باور 1', unitCost: 210);
        $american = $this->makeItem('عدسه امريكي انفستا 12', unitCost: 1900);

        $this->seed(InventoryStockTakeSeeder::class);

        $this->assertEquals(22, (float) $softLens->fresh()->quantity);   // 15 store + 7 OR
        $this->assertEquals(5, (float) $hardLens->fresh()->quantity);    // 4 store + 1 OR
        $this->assertEquals(4, (float) $american->fresh()->quantity);
        $this->assertSame('2027-06-01', $hardLens->fresh()->expiry_date->toDateString());
    }

    public function test_minus_soft_lens_rows_match_the_negative_power_not_the_plus_one(): void
    {
        $minus = $this->makeItem('عدسه لينه باور -7', unitCost: 350);
        $plus = $this->makeItem('عدسه لينه 7', unitCost: 350);

        $this->seed(InventoryStockTakeSeeder::class);

        $this->assertEquals(4, (float) $minus->fresh()->quantity);  // «لينة (ماينص)» 7
        $this->assertEquals(3, (float) $plus->fresh()->quantity);   // 2 store + 1 OR
        $this->assertSame(1, InventoryItem::where('name', 'عدسة لينة باور -1')->count());
    }

    public function test_supply_rows_match_through_aliases_are_summed_and_converted_from_packs(): void
    {
        $syringe = $this->makeItem('سرنجه 5', unitCost: 2);
        $gloves = $this->makeItem('جوانتى 7 معقم', unitCost: 15.5);

        $this->seed(InventoryStockTakeSeeder::class);

        // rows 99 + 100: (7 + 3) boxes × 200
        $this->assertEquals(2000, (float) $syringe->fresh()->quantity);
        $this->assertSame('2026-03-01', $syringe->fresh()->expiry_date->toDateString());
        $this->assertStringContainsString('العبوة: ×200', $syringe->fresh()->notes);
        // 5 boxes × 50; the non-numeric OR note is kept for review
        $this->assertEquals(250, (float) $gloves->fresh()->quantity);
        $this->assertStringContainsString('جرد العمليات: 1 مفتوحه', $gloves->fresh()->notes);
    }

    public function test_variances_are_posted_through_a_single_stock_take_permit_at_unit_cost(): void
    {
        $this->makeItem('عدسه لينه 20', unitCost: 350, quantity: 2);      // surplus 20 × 350 = 7000
        $this->makeItem('رينجر لاكتات', unitCost: 22, quantity: 300);      // 10 × 20 + 8 = 208 → shortage 92 × 22 = 2024

        $this->seed(InventoryStockTakeSeeder::class);

        $this->assertSame(1, StockPermit::where('permit_no', 'like', 'ADJ-%')->count());
        $this->assertEquals(7000.0, (float) Account::where('code', '4220')->value('balance'));
        $this->assertEquals(2024.0, (float) Account::where('code', '5010')->value('balance'));
        $this->assertEquals(7000.0 - 2024.0, (float) Account::where('code', '1051')->value('balance'));
    }

    public function test_unmatched_rows_create_items_and_unlisted_items_are_untouched(): void
    {
        $unlisted = $this->makeItem('ورق A4', unitCost: 1, quantity: 3);

        $this->seed(InventoryStockTakeSeeder::class);

        $anterior = InventoryItem::where('name', 'عدسة أمامية باور 1')->sole();
        $this->assertEquals(1, (float) $anterior->quantity);
        $this->assertSame(ItemUnit::Piece, $anterior->unit);

        $catheter = InventoryItem::where('name', 'قسطرة أورانج')->sole();
        $this->assertEquals(14, (float) $catheter->quantity);
        $this->assertSame(ItemCategory::Medical, $catheter->category);
        $this->assertEquals(0, (float) $catheter->unit_cost);

        $this->assertSame(ItemCategory::Cleaning, InventoryItem::where('name', 'كلوركس')->sole()->category);
        $this->assertSame(3, InventoryItem::where('name', 'like', '(صنف غير مقروء)%')->count());
        $this->assertEquals(3, (float) $unlisted->fresh()->quantity);
    }

    public function test_duplicate_items_receive_the_count_on_the_oldest_one(): void
    {
        $older = $this->makeItem('اتروبين امبول', unitCost: 6);
        Carbon::setTestNow(now()->addMinute());
        $newer = $this->makeItem('اتروبين امبول', unitCost: 9);
        Carbon::setTestNow();

        $this->seed(InventoryStockTakeSeeder::class);

        $this->assertEquals(36, (float) $older->fresh()->quantity);  // 28 + 8 OR
        $this->assertEquals(0, (float) $newer->fresh()->quantity);
    }

    public function test_running_twice_changes_nothing_the_second_time(): void
    {
        $this->makeItem('عدسه لينه 20', unitCost: 350);

        $this->seed(InventoryStockTakeSeeder::class);

        $items = InventoryItem::count();
        $entries = JournalEntry::count();
        $notes = InventoryItem::where('name', 'سرنجات 1 سم')->value('notes');

        $this->seed(InventoryStockTakeSeeder::class);

        $this->assertSame($items, InventoryItem::count());
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame(1, StockPermit::where('permit_no', 'like', 'ADJ-%')->count());
        $this->assertSame($notes, InventoryItem::where('name', 'سرنجات 1 سم')->value('notes'));
    }

    private function makeItem(string $name, float $unitCost, float $quantity = 0): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => ItemCategory::Medical,
            'unit' => ItemUnit::Piece,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'is_active' => true,
        ]);
    }
}
