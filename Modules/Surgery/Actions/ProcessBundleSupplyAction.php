<?php

namespace Modules\Surgery\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\JournalService;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Inventory\Services\InventoryService;

class ProcessBundleSupplyAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Deduct each sub-item from inventory, post accounting entries, and return
     * the bundle as a single supply-line entry ready to be stored in supplies_used.
     */
    public function process(string $bundleId, int $qty, string $dept = 'surgery'): array
    {
        $bundle = SupplyBundle::with('items.inventoryItem')->findOrFail($bundleId);

        $inventoryAccountId = DB::table('accounts')->where('code', '1050')->value('id');
        $costCenter = match ($dept) {
            'lasik' => CostCenter::Lasik,
            'laser' => CostCenter::Laser,
            default => CostCenter::Surgery,
        };

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id) {
                continue;
            }

            $deductQty = (float) $item->qty * $qty;
            $this->inventoryService->adjustQuantity($item->inventory_item_id, -abs($deductQty));

            $cost = round($deductQty * (float) $item->unit_cost, 2);
            if ($cost > 0 && $inventoryAccountId) {
                $category = $item->inventoryItem?->category?->value ?? null;
                $expenseCode = match ($category) {
                    'office' => '5210',
                    'cleaning' => '5220',
                    'maintenance' => '5230',
                    default => '5010',
                };
                $expenseId = DB::table('accounts')->where('code', $expenseCode)->value('id');

                if ($expenseId) {
                    $this->journalService->record([
                        'date' => now()->toDateString(),
                        'description' => "بند: {$item->item_name} — {$bundle->name}",
                        'debit_account_id' => $expenseId,
                        'credit_account_id' => $inventoryAccountId,
                        'amount' => $cost,
                        'source' => JournalSource::SUPPLIES_USED,
                        'reference' => $bundle->name,
                        'cost_center' => $costCenter,
                    ]);
                }
            }
        }

        $this->postBundleChargeEntry($bundle, $qty, $costCenter);

        $this->createStockPermit($bundle, $qty, $dept);

        return [
            'bundle_id' => $bundle->id,
            'inventory_item_id' => '',
            'name' => $bundle->name,
            'qty' => $qty,
            'unit_cost' => (float) $bundle->price,
            'total' => (float) $bundle->price * $qty,
            'is_bundle' => true,
        ];
    }

    /**
     * Dr 2010 (مستحقات الأطباء) / Cr 4210 (إيرادات بيع مستلزمات)
     * Records the bundle price charged against the doctor's dues.
     */
    private function postBundleChargeEntry(SupplyBundle $bundle, int $qty, CostCenter $costCenter): void
    {
        $bundlePrice = round((float) $bundle->price * $qty, 2);
        if ($bundlePrice <= 0) {
            return;
        }

        $doctorPayableId = DB::table('accounts')->where('code', '2010')->value('id');
        $supplyRevenueId = DB::table('accounts')->where('code', '4210')->value('id');

        if (! $doctorPayableId || ! $supplyRevenueId) {
            return;
        }

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => "سعر بند مستلزمات: {$bundle->name} × {$qty}",
            'debit_account_id' => $doctorPayableId,
            'credit_account_id' => $supplyRevenueId,
            'amount' => $bundlePrice,
            'source' => JournalSource::SUPPLIES_USED,
            'reference' => $bundle->name,
            'cost_center' => $costCenter,
        ]);
    }

    private function createStockPermit(SupplyBundle $bundle, int $qty, string $dept): void
    {
        $permit = StockPermit::create([
            'permit_no' => $this->generatePermitNo(),
            'type' => PermitType::Out,
            'department' => $dept,
            'reason' => "استخدام بند: {$bundle->name}",
            'created_by' => auth()->id(),
        ]);

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id) {
                continue;
            }

            $permit->items()->create([
                'item_id' => $item->inventory_item_id,
                'item_name' => $item->item_name,
                'qty' => (float) $item->qty * $qty,
                'unit_cost' => (float) $item->unit_cost,
            ]);
        }
    }

    private function generatePermitNo(): string
    {
        $last = StockPermit::where('type', PermitType::Out->value)->latest()->value('permit_no');
        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return 'OUT-'.date('Y').'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
