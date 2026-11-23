<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceSellPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['inventory.view', 'inventory.write', 'purchases.create', 'purchases.edit'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_creating_a_purchase_invoice_stores_purchase_and_sell_price_and_updates_the_item(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 0]);
        $item = InventoryItem::create([
            'name' => 'عدسة', 'code' => 'LNS-1', 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 10, 'sell_price' => 20,
        ]);

        $this->actingAs($this->user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'paid_amount' => 300,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 30, 'sell_price' => 55],
            ],
        ])->assertRedirect();

        $invoice = PurchaseInvoice::with('items')->latest('id')->first();
        $line = $invoice->items->first();

        $this->assertEquals(30.0, (float) $line->unit_cost);
        $this->assertEquals(55.0, (float) $line->sell_price);

        // Future price now reflects what was entered on this invoice.
        $this->assertEquals(30.0, (float) $item->fresh()->unit_cost);
        $this->assertEquals(55.0, (float) $item->fresh()->sell_price);
    }

    public function test_expiry_date_is_stored_on_the_line_and_synced_to_the_item(): void
    {
        $item = InventoryItem::create([
            'name' => 'محلول', 'code' => 'SOL-1', 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 5, 'sell_price' => 10,
        ]);

        $this->actingAs($this->user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 50,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 5, 'expiry_date' => '2028-03-15'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::with('items')->latest('id')->first();

        $this->assertSame('2028-03-15', $invoice->items->first()->expiry_date->toDateString());
        $this->assertSame('2028-03-15', $item->fresh()->expiry_date->toDateString());

        $this->actingAs($this->user)->put("/purchases/{$invoice->id}", [
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 50,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 5, 'expiry_date' => '2029-01-01'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('2029-01-01', $invoice->fresh()->items->first()->expiry_date->toDateString());
        $this->assertSame('2029-01-01', $item->fresh()->expiry_date->toDateString());
    }

    public function test_expiry_date_must_be_a_valid_date(): void
    {
        $this->actingAs($this->user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'items' => [
                ['item_name' => 'x', 'qty' => 1, 'unit_cost' => 1, 'expiry_date' => 'not-a-date'],
            ],
        ])->assertSessionHasErrors('items.0.expiry_date');
    }

    public function test_updating_prices_on_an_invoice_does_not_change_historical_data_of_other_invoices(): void
    {
        $supplier = Supplier::create(['name' => 'مورد 2', 'is_active' => true, 'balance' => 0]);
        $item = InventoryItem::create([
            'name' => 'قطارة', 'code' => 'DRP-1', 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 5, 'sell_price' => 10,
        ]);

        // First invoice at price 5/10.
        $this->actingAs($this->user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'paid_amount' => 50,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 5, 'sell_price' => 10],
            ],
        ])->assertRedirect();

        $firstInvoice = PurchaseInvoice::with('items')->latest('id')->first();
        $firstLine = $firstInvoice->items->first();

        // Second invoice raises the price to 8/15 — updates the live item.
        $this->actingAs($this->user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'paid_amount' => 80,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 8, 'sell_price' => 15],
            ],
        ])->assertRedirect();

        $this->assertEquals(8.0, (float) $item->fresh()->unit_cost);
        $this->assertEquals(15.0, (float) $item->fresh()->sell_price);

        // The first invoice's own line keeps its original snapshot.
        $this->assertEquals(5.0, (float) $firstLine->fresh()->unit_cost);
        $this->assertEquals(10.0, (float) $firstLine->fresh()->sell_price);
    }
}
