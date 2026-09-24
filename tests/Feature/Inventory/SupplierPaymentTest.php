<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Inventory\Actions\RecordSupplierPaymentAction;
use Modules\Inventory\Models\Supplier;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_cash_supplier_payment_debits_payable_credits_cash_and_reduces_balance(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 1000]);

        app(RecordSupplierPaymentAction::class)->execute([
            'supplier_id' => $supplier->id, 'amount' => 400, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        ]);

        $payable = Account::where('code', '2020')->firstOrFail();
        $cash = Account::where('code', '1010')->firstOrFail();

        $entry = JournalEntry::where('source', 'supplier_payment')->sole();
        $this->assertSame($payable->id, $entry->debit_account_id);
        $this->assertSame($cash->id, $entry->credit_account_id);
        $this->assertEquals(400.00, (float) $entry->amount);
        $this->assertEquals(600.00, (float) $supplier->fresh()->balance);
    }

    public function test_transfer_supplier_payment_credits_bank(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 1000]);

        app(RecordSupplierPaymentAction::class)->execute([
            'supplier_id' => $supplier->id, 'amount' => 400, 'method' => 'transfer', 'paid_at' => now()->toDateString(),
        ]);

        $bank = Account::where('code', '1020')->firstOrFail();
        $entry = JournalEntry::where('source', 'supplier_payment')->sole();
        $this->assertSame($bank->id, $entry->credit_account_id);
    }

    public function test_pay_endpoint_records_payment(): void
    {
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true, 'balance' => 1000]);

        $permission = Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->actingAs($user)
            ->post("/suppliers/{$supplier->id}/pay", ['amount' => 250, 'method' => 'cash'])
            ->assertRedirect();

        $this->assertDatabaseHas('supplier_payments', ['supplier_id' => $supplier->id, 'amount' => 250]);
        $this->assertEquals(750.00, (float) $supplier->fresh()->balance);
    }
}
