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

/**
 * Business rule: an insurance-paid surgery/lasik case still consumes
 * inventory at purchase cost (Dr 5010/5020 / Cr 1051) when supplies are
 * recorded, but the supplies must NEVER be charged against the doctor's
 * payable (2010 / its 22xx sub-ledgers) — insurance doctor fees are a fixed amount, unrelated to
 * supplies (see AutoPostInsuranceDoctorCashPaymentAction).
 */
class InsuranceSuppliesConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function makeBundle(float $unitCost = 45): SupplyBundle
    {
        $item = InventoryItem::create([
            'name' => 'مادة', 'code' => 'ITM-'.uniqid(), 'category' => ItemCategory::Medical,
            'unit' => 'piece', 'quantity' => 100, 'min_quantity' => 1,
            'unit_cost' => $unitCost, 'sell_price' => $unitCost * 1.5,
        ]);

        $bundle = SupplyBundle::create(['name' => 'بند تجريبي', 'code' => 'B-'.uniqid(), 'price' => 200, 'is_active' => true]);
        $bundle->items()->create([
            'inventory_item_id' => $item->id, 'item_name' => $item->name, 'qty' => 2, 'unit_cost' => $unitCost,
        ]);

        return $bundle->load('items.inventoryItem');
    }

    private function makeSurgery(string $payMethod): Surgery
    {
        $surgeon = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $booking = Booking::create([
            'file_no' => 'MRN-'.uniqid(), 'patient_name' => 'مريض', 'dept' => 'surgery',
            'visit_date' => now()->toDateString(), 'price' => 6500, 'paid_amount' => 0, 'doctor_id' => $surgeon->id,
            'pay_method' => $payMethod, 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return Surgery::create(['booking_id' => $booking->id, 'surgeon_id' => $surgeon->id, 'dept' => 'surgery', 'status' => 'in_progress']);
    }

    /** @return array<int, string> every doctor sub-ledger under the 2010 control account */
    private function doctorSubledgerIds(): array
    {
        return Account::where('parent_id', Account::where('code', '2010')->value('id'))->pluck('id')->all();
    }

    public function test_insurance_surgery_consumes_inventory_but_does_not_charge_the_doctor(): void
    {
        $bundle = $this->makeBundle();
        $surgery = $this->makeSurgery('insurance');

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $surgery->id);

        $inventory = Account::where('code', '1051')->firstOrFail();
        $supplyRevenue = Account::where('code', '4070')->firstOrFail();

        // Inventory still consumed at purchase cost, on the insurance cost center (guide §2.5).
        $consumption = JournalEntry::where('credit_account_id', $inventory->id)->get();
        $this->assertGreaterThan(0, $consumption->count());
        $this->assertSame(['CC-INS'], $consumption->pluck('cost_center')->map(fn ($c) => $c?->value ?? $c)->unique()->values()->all());

        // But the doctor-charge entry never posts for an insurance case.
        $this->assertSame(0, JournalEntry::whereIn('debit_account_id', $this->doctorSubledgerIds())->count());
        $this->assertSame(0, JournalEntry::where('credit_account_id', $supplyRevenue->id)->count());
    }

    public function test_cash_surgery_still_charges_the_doctor_for_supplies(): void
    {
        $bundle = $this->makeBundle();
        $surgery = $this->makeSurgery('cash');

        app(ProcessBundleSupplyAction::class)->process($bundle->id, 1, 'surgery', [], $surgery->id);

        $surgeonPayable = DB::table('doctors')->where('id', $surgery->surgeon_id)->value('payable_account_id');
        $supplyRevenue = Account::where('code', '4070')->firstOrFail();

        $entry = JournalEntry::where('credit_account_id', $supplyRevenue->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame($surgeonPayable, $entry->debit_account_id);
        $this->assertSame('2010', Account::find($surgeonPayable)->parent->code);
        $this->assertEquals(200.0, (float) $entry->amount);
    }
}
