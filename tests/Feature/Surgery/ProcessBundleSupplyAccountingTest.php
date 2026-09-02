<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Actions\ProcessBundleSupplyAction;
use Tests\TestCase;

class ProcessBundleSupplyAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function makeBundle(ItemCategory $category, float $unitCost = 10): SupplyBundle
    {
        $item = InventoryItem::create([
            'name' => 'مادة', 'code' => 'ITM-'.uniqid(), 'category' => $category,
            'unit' => 'piece', 'quantity' => 100, 'min_quantity' => 1,
            'unit_cost' => $unitCost, 'sell_price' => $unitCost * 1.5,
        ]);

        $bundle = SupplyBundle::create(['name' => 'بند تجريبي', 'code' => 'B-'.uniqid(), 'price' => 200, 'is_active' => true]);
        $bundle->items()->create([
            'inventory_item_id' => $item->id, 'item_name' => $item->name, 'qty' => 2, 'unit_cost' => $unitCost,
        ]);

        return $bundle->load('items.inventoryItem');
    }

    public function test_office_category_posts_to_admin_expense_not_salaries(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Office);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1);

        $admin = Account::where('code', '5250')->firstOrFail();
        $salaries = Account::where('code', '5210')->firstOrFail();

        $entry = JournalEntry::where('debit_account_id', $admin->id)->first();
        $this->assertNotNull($entry, 'Office-category bundle item should post to 5250, not 5210');
        $this->assertSame(0, JournalEntry::where('debit_account_id', $salaries->id)->count());
    }

    public function test_maintenance_category_posts_to_maintenance_expense_not_utilities(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Maintenance);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1);

        $maintenance = Account::where('code', '5240')->firstOrFail();
        $utilities = Account::where('code', '5230')->firstOrFail();

        $this->assertNotNull(JournalEntry::where('debit_account_id', $maintenance->id)->first());
        $this->assertSame(0, JournalEntry::where('debit_account_id', $utilities->id)->count());
    }

    public function test_bundle_charge_posts_to_doctor_supply_recovery_not_patient_sales_revenue(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Medical);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1);

        $recovery = Account::where('code', '4230')->firstOrFail();
        $patientSales = Account::where('code', '4210')->firstOrFail();
        $doctorPayable = Account::where('code', '2010')->firstOrFail();

        $chargeEntry = JournalEntry::where('credit_account_id', $recovery->id)->first();
        $this->assertNotNull($chargeEntry, 'Bundle charge should credit 4230 (doctor recovery), not 4210 (patient sales)');
        $this->assertSame($doctorPayable->id, $chargeEntry->debit_account_id);
        $this->assertEquals(200.00, (float) $chargeEntry->amount);
        $this->assertSame(0, JournalEntry::where('credit_account_id', $patientSales->id)->count());
    }
}
