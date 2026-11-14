<?php

namespace Tests\Feature\Surgery;

use App\Models\User;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Inventory\Enums\ItemCategory;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Surgery\Models\Surgery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Individual supplies added to a surgery / lasik case are issued through a
 * stock-issue permit (إذن صرف): stock moves, the consumption entry posts,
 * and editing / deleting the line undoes it.
 */
class CaseSuppliesIssuePermitTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $role = Role::create(['name' => 'case_writer', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'surgery.write', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'lasik.write', 'guard_name' => 'web']),
        ]);
        $this->writer = User::factory()->create();
        $this->writer->assignRole($role);
    }

    private function makeItem(string $name, float $quantity = 100, float $unitCost = 5): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'code' => 'ITM-'.uniqid(), 'category' => ItemCategory::Medical,
            'unit' => 'piece', 'quantity' => $quantity, 'min_quantity' => 1,
            'unit_cost' => $unitCost, 'sell_price' => $unitCost * 2,
        ]);
    }

    private function makeCase(string $dept = 'surgery'): Surgery
    {
        $surgeon = Doctor::create(['name' => 'د. جراح', 'fee_type' => 'percentage', 'fee_value' => 100]);
        $booking = Booking::create([
            'file_no' => 'MRN-CASE1', 'patient_name' => 'مريض الحالة', 'dept' => $dept,
            'visit_date' => now()->toDateString(), 'price' => 6500, 'paid_amount' => 0, 'doctor_id' => $surgeon->id,
            'pay_method' => 'cash', 'pay_status' => 'unpaid', 'status' => 'waiting',
        ]);

        return Surgery::create([
            'booking_id' => $booking->id, 'surgeon_id' => $surgeon->id, 'dept' => $dept, 'status' => 'in_progress',
            'supplies_used' => [], 'supply_total' => 0,
        ]);
    }

    /**
     * @param  array<int, array{0: InventoryItem, 1: float}>  $rows
     */
    private function addItems(Surgery $case, array $rows): TestResponse
    {
        return $this->actingAs($this->writer)->post("/{$case->dept->value}/{$case->id}/supplies", [
            'items' => array_map(fn (array $row) => [
                'inventory_item_id' => $row[0]->id,
                'name' => $row[0]->name,
                'qty' => $row[1],
                'unit_cost' => 10,
            ], $rows),
        ]);
    }

    public function test_adding_supplies_issues_a_stock_permit_and_moves_stock(): void
    {
        $case = $this->makeCase();
        $gloves = $this->makeItem('جوانتي');
        $gauze = $this->makeItem('شاش', 50, 4);

        $this->addItems($case, [[$gloves, 3], [$gauze, 2]])->assertSessionHasNoErrors();

        $permit = StockPermit::with('items')->firstOrFail();
        $this->assertSame(PermitType::Out, $permit->type);
        $this->assertSame('surgery', $permit->department->value);
        $this->assertStringContainsString('MRN-CASE1', $permit->reason);
        $this->assertSame('مريض الحالة', $permit->notes);
        $this->assertCount(2, $permit->items);
        $this->assertEquals(5.0, (float) $permit->items->firstWhere('item_id', $gloves->id)->unit_cost);

        $this->assertEquals(97, (float) $gloves->fresh()->quantity);
        $this->assertEquals(48, (float) $gauze->fresh()->quantity);

        $lines = $case->fresh()->supplies_used;
        $this->assertSame($permit->id, $lines[0]['permit_id']);
        $this->assertSame($permit->id, $lines[1]['permit_id']);
    }

    public function test_consumption_is_posted_to_the_department_supplies_cost_account(): void
    {
        $case = $this->makeCase();
        $gloves = $this->makeItem('جوانتي');

        $this->addItems($case, [[$gloves, 3]]);

        $entry = JournalEntry::where('source', JournalSource::SUPPLIES_USED->value)->firstOrFail();
        $this->assertEquals(15.0, (float) $entry->amount);
        $this->assertSame(
            Account::where('code', AccountCode::SURGERY_SUPPLIES_COST->value)->value('id'),
            $entry->debit_account_id,
        );
        $this->assertSame(
            Account::where('code', AccountCode::INVENTORY->value)->value('id'),
            $entry->credit_account_id,
        );
    }

    public function test_lasik_cases_issue_permits_for_the_lasik_department(): void
    {
        $case = $this->makeCase('lasik');
        $gloves = $this->makeItem('جوانتي');

        $this->addItems($case, [[$gloves, 1]])->assertSessionHasNoErrors();

        $this->assertSame('lasik', StockPermit::firstOrFail()->department->value);
        $this->assertSame(
            Account::where('code', AccountCode::LASIK_SUPPLIES_COST->value)->value('id'),
            JournalEntry::firstOrFail()->debit_account_id,
        );
    }

    public function test_insufficient_stock_rejects_the_whole_submission(): void
    {
        $case = $this->makeCase();
        $gloves = $this->makeItem('جوانتي', 2);

        $this->addItems($case, [[$gloves, 5]])->assertSessionHasErrors('items');

        $this->assertSame(0, StockPermit::count());
        $this->assertEquals(2, (float) $gloves->fresh()->quantity);
        $this->assertEmpty($case->fresh()->supplies_used);
    }

    public function test_deleting_the_line_returns_stock_reverses_entries_and_removes_the_permit(): void
    {
        $case = $this->makeCase();
        $gloves = $this->makeItem('جوانتي');
        $this->addItems($case, [[$gloves, 3]]);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$case->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
        $this->assertSame(0, StockPermit::count());
        $this->assertEmpty($case->fresh()->supplies_used);
        $this->assertNotNull(JournalEntry::where('source', JournalSource::SUPPLIES_USED->value)->firstOrFail()->reversed_at);
    }

    public function test_a_legacy_line_without_a_permit_is_deleted_without_touching_stock(): void
    {
        $case = $this->makeCase();
        $gloves = $this->makeItem('جوانتي');
        $case->update([
            'supplies_used' => [[
                'inventory_item_id' => $gloves->id, 'bundle_id' => null, 'permit_id' => null, 'name' => 'جوانتي',
                'qty' => 2, 'unit_cost' => 10, 'total' => 20, 'is_bundle' => false,
            ]],
            'supply_total' => 20,
        ]);

        $this->actingAs($this->writer)
            ->delete("/surgery/{$case->id}/supplies/0", ['line_ref' => $gloves->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals(100, (float) $gloves->fresh()->quantity);
        $this->assertSame(0, JournalEntry::count());
    }
}
