<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Actions\ReceivePurchaseInvoiceAction;
use Modules\Inventory\Models\Supplier;
use Tests\TestCase;

class PurchaseInvoiceAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد تجريبي', 'is_active' => true, 'balance' => 0]);
    }

    public function test_cash_purchase_credits_cash_account(): void
    {
        $supplier = $this->makeSupplier();

        app(ReceivePurchaseInvoiceAction::class)->execute([
            'invoice_no' => 'INV-'.uniqid(),
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 500,
        ], [
            ['item_name' => 'مادة', 'qty' => 5, 'unit_cost' => 100],
        ]);

        $inventory = Account::where('code', '1050')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();

        $entry = JournalEntry::where('source', 'purchase')->sole();
        $this->assertSame($inventory->id, $entry->debit_account_id);
        $this->assertSame($cash->id, $entry->credit_account_id);
        $this->assertEquals(500.00, (float) $entry->amount);
    }

    public function test_credit_purchase_credits_supplier_payable(): void
    {
        $supplier = $this->makeSupplier();

        app(ReceivePurchaseInvoiceAction::class)->execute([
            'invoice_no' => 'INV-'.uniqid(),
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 0,
        ], [
            ['item_name' => 'مادة', 'qty' => 5, 'unit_cost' => 100],
        ]);

        $supplierPayable = Account::where('code', '2020')->firstOrFail();
        $entry = JournalEntry::where('source', 'purchase')->sole();
        $this->assertSame($supplierPayable->id, $entry->credit_account_id);
        $this->assertEquals(500.00, (float) $entry->amount);
    }
}
