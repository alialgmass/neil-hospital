<?php

namespace Tests\Feature\Ledger;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\LedgerService;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountStatementInvoiceLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_invoice_row_carries_its_item_lines(): void
    {
        $this->seed(AccountsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['inventory.view', 'inventory.write', 'purchases.create'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user = User::factory()->create();
        $user->assignRole($role);

        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 0]);
        $item = InventoryItem::create([
            'name' => 'عدسة', 'code' => 'LNS-1', 'category' => 'medical', 'unit' => 'piece',
            'quantity' => 0, 'min_quantity' => 0, 'unit_cost' => 10, 'sell_price' => 20,
        ]);

        $this->actingAs($user)->post('/purchases', [
            'invoice_date' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'paid_amount' => 300,
            'items' => [
                ['item_id' => $item->id, 'item_name' => $item->name, 'qty' => 10, 'unit_cost' => 30, 'sell_price' => 55],
            ],
        ])->assertRedirect();

        $inventoryId = Account::where('code', AccountCode::INVENTORY->value)->value('id');
        $rows = app(LedgerService::class)->accountStatement($inventoryId)['statement'];

        $invoiceRow = collect($rows)->firstWhere('reference', PurchaseInvoice::first()->invoice_no);

        $this->assertNotNull($invoiceRow);
        $this->assertCount(1, $invoiceRow['lines']);
        $this->assertSame('عدسة', $invoiceRow['lines'][0]['name']);
        $this->assertEquals(10.0, $invoiceRow['lines'][0]['qty']);
        $this->assertEquals(30.0, $invoiceRow['lines'][0]['unit_price']);
        $this->assertEquals(300.0, $invoiceRow['lines'][0]['total']);
    }
}
