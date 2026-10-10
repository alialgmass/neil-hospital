<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\ItemSalesInvoice;
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
                fn ($row) => $row['type'] === 'out' && $row['reference_no'] === 'ISS-001' && $row['source'] === 'issue'
            )));
    }

    private function makePermit(InventoryItem $item, string $no, string $type, ?string $reason, string $date, float $qty): StockPermit
    {
        $permit = StockPermit::create([
            'permit_no' => $no, 'type' => $type, 'reason' => $reason, 'created_by' => $this->user->id,
        ]);
        $permit->forceFill(['created_at' => $date])->save();
        $permit->items()->create(['item_id' => $item->id, 'item_name' => $item->name, 'qty' => $qty, 'unit_cost' => $item->unit_cost]);

        return $permit;
    }

    private function makeSale(InventoryItem $item, string $no, string $date, float $qty, float $unitPrice): ItemSalesInvoice
    {
        $invoice = ItemSalesInvoice::create([
            'invoice_no' => $no, 'invoice_date' => $date, 'customer_name' => 'عميل', 'pay_method' => 'cash',
            'subtotal' => $qty * $unitPrice, 'discount' => 0, 'total' => $qty * $unitPrice, 'cost_total' => $qty * (float) $item->unit_cost,
        ]);
        $invoice->items()->create([
            'item_id' => $item->id, 'item_name' => $item->name, 'qty' => $qty,
            'unit_price' => $unitPrice, 'unit_cost' => $item->unit_cost, 'line_total' => $qty * $unitPrice,
        ]);

        return $invoice;
    }

    public function test_classifies_every_movement_source_and_summarises_by_source_and_item(): void
    {
        $drops = InventoryItem::create(['name' => 'قطرة', 'unit' => 'piece', 'quantity' => 100, 'unit_cost' => 20]);

        $this->makePermit($drops, 'IN-001', 'in', null, '2026-06-05', 10);
        $this->makePermit($drops, 'OUT-001', 'out', 'استهلاك تشغيلي', '2026-06-06', 3);
        $this->makePermit($drops, 'OUT-002', 'out', 'استخدام بند: حزمة مياه بيضاء', '2026-06-07', 2);
        $this->makePermit($drops, 'ADJ-00001', 'out', 'تسوية جرد', '2026-06-08', 1);
        $this->makeSale($drops, 'SAL-2026-00001', '2026-06-09', 4, 30);

        $this->actingAs($this->user)
            ->get('/reports/inventory-movement?period=month&month=2026-06')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.from', '2026-06-01')
                ->where('filters.to', '2026-06-30')
                ->where('data.rows', fn ($rows) => collect($rows)->pluck('source', 'reference_no')->all() === [
                    'SAL-2026-00001' => 'sale',
                    'ADJ-00001' => 'stock_take',
                    'OUT-002' => 'bundle',
                    'OUT-001' => 'issue',
                    'IN-001' => 'stock_in',
                ])
                ->where('data.salesValue', 120)
                ->where('data.byItem', fn ($items) => count($items) === 1
                    && (float) $items[0]['in_qty'] === 10.0
                    && (float) $items[0]['out_qty'] === 10.0)
                ->where('data.bySource', fn ($sources) => collect($sources)->firstWhere('source', 'sale')['total'] == 80)
            );
    }

    public function test_day_period_and_item_and_source_filters(): void
    {
        $drops = InventoryItem::create(['name' => 'قطرة', 'unit' => 'piece', 'quantity' => 100, 'unit_cost' => 20]);
        $gauze = InventoryItem::create(['name' => 'شاش', 'unit' => 'piece', 'quantity' => 100, 'unit_cost' => 2]);

        $this->makeSale($drops, 'SAL-2026-00001', '2026-06-09', 1, 30);
        $this->makeSale($gauze, 'SAL-2026-00002', '2026-06-09', 1, 5);
        $this->makeSale($drops, 'SAL-2026-00003', '2026-06-10', 1, 30);
        $this->makePermit($drops, 'OUT-001', 'out', null, '2026-06-09 10:00:00', 1);

        $this->actingAs($this->user)
            ->get("/reports/inventory-movement?period=day&date=2026-06-09&item_id={$drops->id}&source=sale")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('data.rows', fn ($rows) => collect($rows)->pluck('reference_no')->all() === ['SAL-2026-00001'])
            );
    }

    public function test_defaults_to_today_and_rejects_unknown_source(): void
    {
        $this->actingAs($this->user)
            ->get('/reports/inventory-movement')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.period', 'day')
                ->where('filters.from', today()->toDateString())
            );

        $this->actingAs($this->user)
            ->get('/reports/inventory-movement?source=bogus')
            ->assertSessionHasErrors('source');
    }
}
