<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Surgery\Actions\ProcessBundleSupplyAction;
use Modules\Surgery\Models\Surgery;
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

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $this->makeSurgery()->id);

        $admin = Account::where('code', '5250')->firstOrFail();
        $salaries = Account::where('code', '5210')->firstOrFail();

        $entry = JournalEntry::where('debit_account_id', $admin->id)->first();
        $this->assertNotNull($entry, 'Office-category bundle item should post to 5250, not 5210');
        $this->assertSame(0, JournalEntry::where('debit_account_id', $salaries->id)->count());
    }

    public function test_maintenance_category_posts_to_maintenance_expense_not_utilities(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Maintenance);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $this->makeSurgery()->id);

        $maintenance = Account::where('code', '5240')->firstOrFail();
        $utilities = Account::where('code', '5230')->firstOrFail();

        $this->assertNotNull(JournalEntry::where('debit_account_id', $maintenance->id)->first());
        $this->assertSame(0, JournalEntry::where('debit_account_id', $utilities->id)->count());
    }

    public function test_bundle_charge_posts_to_supplies_sale_revenue_not_patient_sales_or_contra_expense(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Medical);

        $surgery = $this->makeSurgery();
        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $surgery->id);

        $supplyRevenue = Account::where('code', '4070')->firstOrFail();
        $patientSales = Account::where('code', '4210')->firstOrFail();
        $surgeonPayable = DB::table('doctors')->where('id', $surgery->surgeon_id)->value('payable_account_id');

        $chargeEntry = JournalEntry::where('credit_account_id', $supplyRevenue->id)->first();
        $this->assertNotNull($chargeEntry, 'Bundle charge should credit 4070 (supplies-sale revenue), not 5115 or 4210');
        $this->assertSame($surgeonPayable, $chargeEntry->debit_account_id, 'charged to the surgeon\'s own 22xx sub-ledger');
        $this->assertSame($surgery->booking->file_no, $chargeEntry->reference);
        $this->assertEquals(200.00, (float) $chargeEntry->amount);
        $this->assertSame(0, JournalEntry::where('credit_account_id', $patientSales->id)->count());
    }

    public function test_lasik_consumption_is_costed_to_lasik_supplies(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Medical, 765);

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'lasik', [], $this->makeSurgery('lasik')->id);

        $this->assertEquals(1530.0, (float) Account::where('code', '5020')->value('balance'));
        $this->assertEquals(0.0, (float) Account::where('code', '5010')->value('balance'));
    }

    public function test_empty_selection_charges_bundle_price_but_deducts_no_stock(): void
    {
        $bundle = $this->makeBundle(ItemCategory::Medical);
        $item = $bundle->items->first()->inventoryItem;
        $stockBefore = (float) $item->fresh()->quantity;

        $surgery = $this->makeSurgery();
        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $surgery->id, true);

        $this->assertEquals($stockBefore, (float) $item->fresh()->quantity);
        $supplyRevenue = Account::where('code', '4070')->firstOrFail();
        $this->assertEquals(200.00, (float) JournalEntry::where('credit_account_id', $supplyRevenue->id)->value('amount'));
        $this->assertSame(0, JournalEntry::where('source', 'supplies_used')->where('description', 'like', 'بند:%')->count());
    }

    private function makeSurgery(string $dept = 'surgery'): Surgery
    {
        $surgeon = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => $dept,
            'visit_date' => now()->toDateString(), 'price' => 10000, 'paid_amount' => 10000, 'doctor_id' => $surgeon->id,
            'pay_method' => 'cash', 'pay_status' => 'paid', 'status' => 'waiting',
        ]);

        return Surgery::create(['booking_id' => $booking->id, 'surgeon_id' => $surgeon->id, 'dept' => $dept, 'status' => 'in_progress']);
    }
}
