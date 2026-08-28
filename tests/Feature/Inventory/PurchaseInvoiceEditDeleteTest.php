<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Actions\ReceivePurchaseInvoiceAction;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $storeKeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        Permission::firstOrCreate(['name' => 'inventory.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.delete', 'guard_name' => 'web']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['inventory.view', 'inventory.write', 'purchases.edit', 'purchases.delete']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $storeKeeperRole = Role::firstOrCreate(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperRole->givePermissionTo(['inventory.view', 'inventory.write']);
        $this->storeKeeper = User::factory()->create();
        $this->storeKeeper->assignRole($storeKeeperRole);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد تجريبي', 'is_active' => true, 'balance' => 0]);
    }

    private function makeInvoiceWithItem(float $qty = 10, float $unitCost = 20): array
    {
        $supplier = $this->makeSupplier();
        $item = InventoryItem::create([
            'name' => 'قطن طبي', 'code' => 'ITM-1', 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 0, 'sell_price' => 0,
        ]);

        $invoice = app(ReceivePurchaseInvoiceAction::class)->execute([
            'invoice_no' => 'INV-'.uniqid(),
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 0,
        ], [
            ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => $qty, 'unit_cost' => $unitCost],
        ]);

        return [$invoice, $item, $supplier];
    }

    // ── Item search ──

    public function test_item_search_returns_matching_items_by_name_or_code(): void
    {
        InventoryItem::create(['name' => 'شاش طبي', 'code' => 'GZ-1', 'category' => 'medical', 'unit' => 'piece', 'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 5, 'sell_price' => 8]);
        InventoryItem::create(['name' => 'قفازات', 'code' => 'GL-1', 'category' => 'medical', 'unit' => 'piece', 'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 3, 'sell_price' => 5]);

        $response = $this->actingAs($this->storeKeeper)->getJson('/purchases/items/search?q=شاش');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['code' => 'GZ-1']);
    }

    public function test_item_search_with_empty_query_returns_empty_array(): void
    {
        $response = $this->actingAs($this->storeKeeper)->getJson('/purchases/items/search?q=');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_item_search_requires_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/purchases/items/search?q=test')->assertForbidden();
    }

    // ── Permission gating ──

    public function test_store_keeper_without_purchases_edit_permission_cannot_update_an_invoice(): void
    {
        [$invoice] = $this->makeInvoiceWithItem();

        $this->actingAs($this->storeKeeper)
            ->put("/purchases/{$invoice->id}", $this->updatePayload($invoice))
            ->assertForbidden();
    }

    public function test_store_keeper_without_purchases_delete_permission_cannot_delete_an_invoice(): void
    {
        [$invoice] = $this->makeInvoiceWithItem();

        $this->actingAs($this->storeKeeper)
            ->delete("/purchases/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_admin_can_update_an_invoice(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);

        $this->actingAs($this->admin)
            ->put("/purchases/{$invoice->id}", $this->updatePayload($invoice, [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 15, 'unit_cost' => 20]],
            ]))
            ->assertRedirect();

        $this->assertEquals(15, (float) $item->fresh()->quantity);
        $this->assertEquals(300, (float) $invoice->fresh()->total);
    }

    public function test_admin_can_delete_an_invoice(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);

        $this->actingAs($this->admin)
            ->delete("/purchases/{$invoice->id}")
            ->assertRedirect();

        $this->assertEquals(0, (float) $item->fresh()->quantity);
        $this->assertDatabaseMissing((new PurchaseInvoice)->getTable(), ['id' => $invoice->id]);
    }

    // ── Stock reconciliation ──

    public function test_updating_quantity_adjusts_inventory_correctly(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);
        $this->assertEquals(10, (float) $item->fresh()->quantity);

        $this->actingAs($this->admin)->put("/purchases/{$invoice->id}", $this->updatePayload($invoice, [
            'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 4, 'unit_cost' => 20]],
        ]))->assertRedirect();

        $this->assertEquals(4, (float) $item->fresh()->quantity);
    }

    public function test_update_is_blocked_when_stock_already_partly_consumed_below_new_quantity(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);

        // Simulate 8 units already dispensed elsewhere — only 2 left in stock.
        $item->decrement('quantity', 8);
        $this->assertEquals(2, (float) $item->fresh()->quantity);

        $this->actingAs($this->admin)
            ->put("/purchases/{$invoice->id}", $this->updatePayload($invoice, [
                'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 5, 'unit_cost' => 20]],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertEquals(2, (float) $item->fresh()->quantity);
    }

    public function test_delete_is_blocked_when_stock_already_partly_consumed(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);

        $item->decrement('quantity', 9);
        $this->assertEquals(1, (float) $item->fresh()->quantity);

        $this->actingAs($this->admin)
            ->delete("/purchases/{$invoice->id}")
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas((new PurchaseInvoice)->getTable(), ['id' => $invoice->id]);
        $this->assertEquals(1, (float) $item->fresh()->quantity);
    }

    // ── Accounting reversal ──

    public function test_deleting_a_posted_invoice_reverses_its_journal_entry(): void
    {
        [$invoice] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);
        $original = JournalEntry::where('reference', $invoice->invoice_no)->sole();

        $this->actingAs($this->admin)->delete("/purchases/{$invoice->id}")->assertRedirect();

        $this->assertNotNull($original->fresh()->reversed_at);
        $this->assertDatabaseHas((new JournalEntry)->getTable(), [
            'reversal_of_id' => $original->id,
        ]);
    }

    public function test_updating_a_posted_invoice_reverses_and_reposts_the_journal_entry(): void
    {
        [$invoice, $item] = $this->makeInvoiceWithItem(qty: 10, unitCost: 20);
        $original = JournalEntry::where('reference', $invoice->invoice_no)->sole();

        $this->actingAs($this->admin)->put("/purchases/{$invoice->id}", $this->updatePayload($invoice, [
            'items' => [['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 20, 'unit_cost' => 20]],
        ]))->assertRedirect();

        $this->assertNotNull($original->fresh()->reversed_at);

        $inventory = Account::where('code', '1050')->firstOrFail();
        $newEntry = JournalEntry::where('reference', $invoice->invoice_no)
            ->where('source', 'purchase')
            ->whereNull('reversed_at')
            ->sole();
        $this->assertSame($inventory->id, $newEntry->debit_account_id);
        $this->assertEquals(400.00, (float) $newEntry->amount);
    }

    private function updatePayload(PurchaseInvoice $invoice, array $overrides = []): array
    {
        return array_merge([
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'discount' => 0,
            'paid_amount' => 0,
            'items' => $invoice->items->map(fn ($i) => [
                'item_id' => $i->item_id,
                'item_name' => $i->item_name,
                'qty' => $i->qty,
                'unit_cost' => $i->unit_cost,
            ])->all(),
        ], $overrides);
    }
}
