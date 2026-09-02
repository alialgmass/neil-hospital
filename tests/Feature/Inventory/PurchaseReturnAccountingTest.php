<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Actions\ReceivePurchaseInvoiceAction;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\PurchaseReturnService;
use Tests\TestCase;

class PurchaseReturnAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_return_on_credit_invoice_posts_journal_entry(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 0]);

        $invoice = app(ReceivePurchaseInvoiceAction::class)->execute([
            'invoice_no' => 'INV-'.uniqid(),
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'paid_amount' => 0,
        ], [
            ['item_id' => null, 'item_name' => 'مادة', 'qty' => 10, 'unit_cost' => 50],
        ]);

        app(PurchaseReturnService::class)->processReturn([
            'invoice_id' => $invoice->id,
            'items' => [
                ['item_id' => null, 'qty' => 2, 'unit_cost' => 50],
            ],
        ]);

        $supplierPayable = Account::where('code', '2020')->firstOrFail();
        $inventory = Account::where('code', '1050')->firstOrFail();

        $returnEntry = JournalEntry::where('reference', 'RET-'.$invoice->invoice_no)->sole();
        $this->assertSame($supplierPayable->id, $returnEntry->debit_account_id);
        $this->assertSame($inventory->id, $returnEntry->credit_account_id);
        $this->assertEquals(100.00, (float) $returnEntry->amount);
    }
}
