<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\TreasuryEntry;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\ItemSalesInvoice;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemSalesInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $storeKeeper;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        Permission::firstOrCreate(['name' => 'inventory.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.write', 'guard_name' => 'web']);

        $storeKeeperRole = Role::firstOrCreate(['name' => 'store_keeper', 'guard_name' => 'web']);
        $storeKeeperRole->givePermissionTo(['inventory.view', 'inventory.write']);
        $this->storeKeeper = User::factory()->create();
        $this->storeKeeper->assignRole($storeKeeperRole);

        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo(['inventory.view']);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($viewerRole);
    }

    private function makeItem(string $name, float $quantity = 10, float $unitCost = 20, float $sellPrice = 30): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => 'medical', 'unit' => 'piece',
            'quantity' => $quantity, 'min_quantity' => 0, 'unit_cost' => $unitCost, 'sell_price' => $sellPrice,
        ]);
    }

    /**
     * @param  array<int, array{item_id: string, qty: float, unit_price: float}>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'invoice_date' => '2026-10-04',
            'customer_name' => 'محمد علي',
            'pay_method' => 'cash',
            'discount' => 0,
            'items' => $items,
        ], $overrides);
    }

    private function accountBalance(string $code): float
    {
        return (float) Account::where('code', $code)->value('balance');
    }

    public function test_creating_invoice_deducts_stock_and_posts_revenue_and_cost(): void
    {
        $drops = $this->makeItem('قطرة', 10, 20, 30);
        $gauze = $this->makeItem('شاش', 5, 4, 10);

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([
                ['item_id' => $drops->id, 'qty' => 2, 'unit_price' => 30],
                ['item_id' => $gauze->id, 'qty' => 1, 'unit_price' => 10],
            ], ['discount' => 5]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = ItemSalesInvoice::with('items')->sole();

        $this->assertMatchesRegularExpression('/^SAL-\d{4}-00001$/', $invoice->invoice_no);
        $this->assertSame(70.0, (float) $invoice->subtotal);
        $this->assertSame(65.0, (float) $invoice->total);
        $this->assertSame(44.0, (float) $invoice->cost_total);
        $this->assertCount(2, $invoice->items);

        $this->assertSame(8.0, (float) $drops->fresh()->quantity);
        $this->assertSame(4.0, (float) $gauze->fresh()->quantity);

        $this->assertSame(65.0, $this->accountBalance('4210'));
        $this->assertSame(65.0, $this->accountBalance('1010'));
        $this->assertSame(-44.0, $this->accountBalance('1051'));
        $this->assertSame(3, JournalEntry::where('source', JournalSource::ITEM_SALE)->count());

        $treasury = TreasuryEntry::sole();
        $this->assertSame(65.0, (float) $treasury->amount);
    }

    public function test_card_payment_is_posted_to_bank(): void
    {
        $drops = $this->makeItem('قطرة');

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([
                ['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30],
            ], ['pay_method' => 'card']))
            ->assertSessionHasNoErrors();

        $this->assertSame(30.0, $this->accountBalance('1020'));
        $this->assertSame(0.0, $this->accountBalance('1010'));
    }

    public function test_unit_cost_comes_from_inventory_not_request(): void
    {
        $drops = $this->makeItem('قطرة', 10, 20, 30);

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([
                ['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30, 'unit_cost' => 1],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(20.0, (float) ItemSalesInvoice::sole()->items()->first()->unit_cost);
    }

    public function test_quantity_above_stock_is_rejected_across_duplicate_lines(): void
    {
        $drops = $this->makeItem('قطرة', 3);

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([
                ['item_id' => $drops->id, 'qty' => 2, 'unit_price' => 30],
                ['item_id' => $drops->id, 'qty' => 2, 'unit_price' => 30],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(3.0, (float) $drops->fresh()->quantity);
        $this->assertSame(0, ItemSalesInvoice::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_discount_larger_than_subtotal_is_rejected(): void
    {
        $drops = $this->makeItem('قطرة');

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([
                ['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30],
            ], ['discount' => 31]))
            ->assertSessionHasErrors('discount');

        $this->assertSame(0, ItemSalesInvoice::count());
    }

    public function test_insurance_pay_method_and_missing_fields_are_rejected(): void
    {
        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([], ['pay_method' => 'insurance', 'customer_name' => '']))
            ->assertSessionHasErrors(['pay_method', 'customer_name', 'items']);
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        $drops = $this->makeItem('قطرة');

        $this->actingAs($this->storeKeeper);
        $this->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30]]));
        $this->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30]]));

        $this->assertSame(
            ['00001', '00002'],
            ItemSalesInvoice::orderBy('invoice_no')->pluck('invoice_no')->map(fn (string $no) => substr($no, -5))->all(),
        );
    }

    public function test_viewer_cannot_create_invoice(): void
    {
        $drops = $this->makeItem('قطرة');

        $this->actingAs($this->viewer)
            ->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30]]))
            ->assertForbidden();

        $this->assertSame(10.0, (float) $drops->fresh()->quantity);
    }

    public function test_index_lists_invoices_in_range_with_totals(): void
    {
        $drops = $this->makeItem('قطرة', 10, 20, 30);

        $this->actingAs($this->storeKeeper);
        $this->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 2, 'unit_price' => 30]]));
        $this->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30]], ['invoice_date' => '2026-09-01']));

        $this->actingAs($this->viewer)
            ->get('/item-sales?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/ItemSales')
                ->has('invoices.data', 1)
                ->where('totals.count', 1)
                ->where('totals.total', 60)
                ->where('totals.profit', 20)
                ->has('selectableItems', 1)
            );
    }

    public function test_legacy_sales_invoices_url_redirects_to_item_sales(): void
    {
        $this->actingAs($this->viewer)
            ->get('/sales-invoices')
            ->assertRedirect('/item-sales');
    }

    public function test_show_renders_print_page(): void
    {
        $drops = $this->makeItem('قطرة');

        $this->actingAs($this->storeKeeper)
            ->post('/item-sales', $this->payload([['item_id' => $drops->id, 'qty' => 1, 'unit_price' => 30]]));

        $invoice = ItemSalesInvoice::sole();

        $this->actingAs($this->viewer)
            ->get("/item-sales/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/ItemSalesInvoicePrint')
                ->where('invoice.invoice_no', $invoice->invoice_no)
                ->has('invoice.items', 1)
            );
    }
}
