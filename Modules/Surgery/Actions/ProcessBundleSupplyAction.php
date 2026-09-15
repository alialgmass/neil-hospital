<?php

namespace Modules\Surgery\Actions;

use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Booking\Enums\PayMethod;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Inventory\Services\InventoryService;
use Modules\Surgery\Models\Surgery;

class ProcessBundleSupplyAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * Deduct each sub-item from inventory, post accounting entries, and return
     * the bundle as a single supply-line entry ready to be stored in supplies_used.
     *
     * @param  array<array{inventory_item_id: string, qty: float}>  $selectedItems
     *                                                                              When provided, only those items are deducted with their given quantities.
     *                                                                              When empty, all bundle items are deducted using bundle defaults × $qty.
     */
    public function process(string $bundleId, int $qty, string $dept = 'surgery', array $selectedItems = [], ?string $surgeryId = null): array
    {
        $bundle = SupplyBundle::with('items.inventoryItem')->findOrFail($bundleId);

        $inventoryAccountId = $this->accountResolver->id(AccountCode::INVENTORY);
        $costCenter = match ($dept) {
            'lasik' => CostCenter::Lasik,
            'laser' => CostCenter::Laser,
            default => CostCenter::Surgery,
        };

        // Build a lookup of user-selected items: inventory_item_id → custom qty
        $selectedMap = [];
        foreach ($selectedItems as $si) {
            if (! empty($si['inventory_item_id'])) {
                $selectedMap[$si['inventory_item_id']] = max(0.01, (float) ($si['qty'] ?? 0));
            }
        }

        // Create the stock-permit shell first — its id anchors the
        // idempotency keys for this specific bundle-consumption event, so a
        // retried/duplicate request can't double-post the same journal lines.
        $permit = $this->createStockPermit($bundle, $qty, $dept, $selectedMap);

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id) {
                continue;
            }

            // Skip items the user did not select when a selection was made
            if (! empty($selectedMap) && ! isset($selectedMap[$item->inventory_item_id])) {
                continue;
            }

            $deductQty = $selectedMap[$item->inventory_item_id]
                ?? (float) $item->qty * $qty;

            $this->inventoryService->adjustQuantity($item->inventory_item_id, -abs($deductQty));

            $cost = round($deductQty * (float) $item->unit_cost, 2);
            if ($cost > 0) {
                $category = $item->inventoryItem?->category;
                $expenseId = $this->accountResolver->id(AccountCode::expenseAccountForCategory($category));

                $this->journalService->record([
                    'date' => now()->toDateString(),
                    'description' => "بند: {$item->item_name} — {$bundle->name}",
                    'debit_account_id' => $expenseId,
                    'credit_account_id' => $inventoryAccountId,
                    'amount' => $cost,
                    'source' => JournalSource::SUPPLIES_USED,
                    'reference' => $bundle->name,
                    'idempotency_key' => "bundle_supply_item:{$permit->id}:{$item->inventory_item_id}",
                    'cost_center' => $costCenter,
                ]);
            }
        }

        // Insurance doctor fees are a fixed amount (Dr 5130 / Cr 1010, see
        // AutoPostInsuranceDoctorCashPaymentAction) and never accrue to 2010
        // — so supplies must never be charged against the doctor's payable
        // for an insurance-paid case either. Supplies are still consumed
        // from inventory above regardless of pay method.
        if (! $this->isInsurancePaid($surgeryId)) {
            $this->postBundleChargeEntry($bundle, $qty, $costCenter, $permit->id);
        }

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
     * Dr 2010 (مستحقات الأطباء) / Cr 4070 (إيراد بيع مستهلكات للأطباء)
     * Records the bundle price charged against the doctor's dues at selling
     * price — per الدليل المحاسبي v2.0's final worked example, this is
     * revenue to the center (the spread over purchase cost is the center's
     * supplies profit), NOT a patient sale (4210) and NOT a contra-expense.
     */
    private function postBundleChargeEntry(SupplyBundle $bundle, int $qty, CostCenter $costCenter, string $permitId): void
    {
        $bundlePrice = round((float) $bundle->price * $qty, 2);
        if ($bundlePrice <= 0) {
            return;
        }

        $doctorPayableId = $this->accountResolver->id(AccountCode::DOCTOR_PAYABLE);
        $revenueId = $this->accountResolver->id(AccountCode::SUPPLIES_SALE_REVENUE);

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => "سعر بند مستلزمات: {$bundle->name} × {$qty}",
            'debit_account_id' => $doctorPayableId,
            'credit_account_id' => $revenueId,
            'amount' => $bundlePrice,
            'source' => JournalSource::SUPPLIES_USED,
            'reference' => $bundle->name,
            'idempotency_key' => "bundle_supply_charge:{$permitId}",
            'cost_center' => $costCenter,
        ]);
    }

    private function isInsurancePaid(?string $surgeryId): bool
    {
        if ($surgeryId === null) {
            return false;
        }

        return Surgery::with('booking:id,pay_method')->find($surgeryId)?->booking?->pay_method === PayMethod::Insurance;
    }

    private function createStockPermit(SupplyBundle $bundle, int $qty, string $dept, array $selectedMap = []): StockPermit
    {
        $permit = StockPermit::create([
            'permit_no' => $this->generatePermitNo(),
            'type' => PermitType::Out,
            'department' => $dept,
            'reason' => "استخدام بند: {$bundle->name}",
            'created_by' => auth()->id(),
        ]);

        $hasSelection = ! empty($selectedMap);

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id) {
                continue;
            }

            if ($hasSelection && ! isset($selectedMap[$item->inventory_item_id])) {
                continue;
            }

            $permitQty = $hasSelection
                ? $selectedMap[$item->inventory_item_id]
                : (float) $item->qty * $qty;

            $permit->items()->create([
                'item_id' => $item->inventory_item_id,
                'item_name' => $item->item_name,
                'qty' => $permitQty,
                'unit_cost' => (float) $item->unit_cost,
            ]);
        }

        return $permit;
    }

    private function generatePermitNo(): string
    {
        $last = StockPermit::where('type', PermitType::Out->value)->latest()->value('permit_no');
        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return 'OUT-'.date('Y').'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
