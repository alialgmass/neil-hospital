<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\StockPermit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryMovementReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'inventory.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('inventory.view');

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_purchase_invoice_receipts_appear_as_in_movements(): void
    {
        // Regression test: PurchaseInvoiceService::create() increments inventory
        // quantity directly without ever creating a stock_permit row, so purchase
        // receipts — the primary source of stock inflow — were invisible to this
        // report even though the report claims to show "الوارد والصادر" (in & out).
        $item = InventoryItem::create(['name' => 'شاش طبي', 'unit' => 'box', 'quantity' => 0, 'unit_cost' => 10]);

        $invoice = PurchaseInvoice::create([
            'invoice_no' => 'PINV-001',
            'invoice_date' => '2026-06-15',
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'paid_amount' => 500,
            'remaining' => 0,
            'status' => 'posted',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create([
            'item_id' => $item->id,
            'item_name' => 'شاش طبي',
            'qty' => 50,
            'unit_cost' => 10,
            'total' => 500,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/inventory-movement?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['type'] === 'in' && $row['reference_no'] === 'PINV-001' && (float) $row['total'] === 500.0
            )));
    }

    public function test_stock_issue_permits_still_appear_as_out_movements(): void
    {
        $item = InventoryItem::create(['name' => 'قفازات', 'unit' => 'box', 'quantity' => 100, 'unit_cost' => 5]);

        $permit = StockPermit::create([
            'permit_no' => 'ISS-001',
            'type' => 'out',
            'department' => 'clinic',
            'created_by' => $this->user->id,
        ]);
        $permit->forceFill(['created_at' => '2026-06-10'])->save();
        $permit->items()->create([
            'item_id' => $item->id,
            'item_name' => 'قفازات',
            'qty' => 10,
            'unit_cost' => 5,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/inventory-movement?from=2026-06-01&to=2026-06-30');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('data.rows', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['type'] === 'out' && $row['reference_no'] === 'ISS-001'
            )));
    }
}
