<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\SupplyBundle;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventorySearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'inventory.view', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo('inventory.view');
    }

    private function makeInvoice(string $invoiceNo, string $supplierName, string $status = 'posted'): PurchaseInvoice
    {
        $supplier = Supplier::create(['name' => $supplierName]);

        return PurchaseInvoice::create([
            'invoice_no' => $invoiceNo, 'supplier_id' => $supplier->id, 'invoice_date' => today(),
            'subtotal' => 100, 'discount' => 0, 'total' => 100, 'paid_amount' => 0, 'remaining' => 100,
            'status' => $status,
        ]);
    }

    public function test_purchase_invoices_are_searchable_by_number_and_supplier(): void
    {
        $this->makeInvoice('PI-AAA', 'Nile Pharma');
        $this->makeInvoice('PI-BBB', 'Delta Optics');

        $this->actingAs($this->user)->get('/purchases?search=Delta')
            ->assertInertia(fn ($page) => $page->has('invoices.data', 1)->where('invoices.data.0.invoice_no', 'PI-BBB'));

        $this->actingAs($this->user)->get('/purchases?search=PI-AAA')
            ->assertInertia(fn ($page) => $page->has('invoices.data', 1)->where('invoices.data.0.invoice_no', 'PI-AAA'));
    }

    public function test_supply_bundles_are_searchable_by_name_and_code(): void
    {
        SupplyBundle::create(['name' => 'Cataract kit', 'code' => 'CK-1', 'price' => 10]);
        SupplyBundle::create(['name' => 'Lasik kit', 'code' => 'LK-1', 'price' => 10]);

        $this->actingAs($this->user)->get('/supply-bundles?search=Lasik')
            ->assertInertia(fn ($page) => $page->has('bundles.data', 1)->where('bundles.data.0.code', 'LK-1'));

        $this->actingAs($this->user)->get('/supply-bundles?search=CK-')
            ->assertInertia(fn ($page) => $page->has('bundles.data', 1)->where('bundles.data.0.name', 'Cataract kit'));
    }

    public function test_stock_permits_and_issues_are_searchable(): void
    {
        StockPermit::create(['permit_no' => 'SP-100', 'type' => 'out', 'reason' => 'surgery use']);
        StockPermit::create(['permit_no' => 'SP-200', 'type' => 'out', 'reason' => 'ward use']);

        $this->actingAs($this->user)->get('/stock-permits?search=SP-200')
            ->assertInertia(fn ($page) => $page->has('permits.data', 1)->where('permits.data.0.permit_no', 'SP-200'));

        $this->actingAs($this->user)->get('/stock-issue?search=surgery')
            ->assertInertia(fn ($page) => $page->has('permits.data', 1)->where('permits.data.0.permit_no', 'SP-100'));
    }

    public function test_purchase_returns_are_searchable(): void
    {
        $this->makeInvoice('PR-AAA', 'Nile Pharma', 'cancelled');
        $this->makeInvoice('PR-BBB', 'Delta Optics', 'cancelled');

        $this->actingAs($this->user)->get('/purchase-returns?search=Nile')
            ->assertInertia(fn ($page) => $page->has('returns.data', 1)->where('returns.data.0.invoice_no', 'PR-AAA'));
    }
}
