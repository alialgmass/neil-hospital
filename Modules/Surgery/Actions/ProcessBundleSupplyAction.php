<?php

namespace Modules\Surgery\Actions;

use App\Enums\Department;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\CostCenter;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Inventory\Enums\PermitType;
use Modules\Inventory\Models\StockPermit;
use Modules\Inventory\Models\SupplyBundle;
use Modules\Inventory\Services\InventoryService;
use Modules\Surgery\Models\Surgery;

class ProcessBundleSupplyAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
        private readonly SubledgerAccountResolver $subledgers,
    ) {}

    /**
     * Deduct each sub-item from inventory, post accounting entries, and return
     * the bundle as a single supply-line entry ready to be stored in supplies_used.
     *
     * Inventory consumption posts regardless of pay method, at PURCHASE price,
     * to the department's cost account (Dr 5010 surgery / 5020 lasik — Cr
     * 1051). The doctor-charge leg (Dr doctor sub-ledger 22xx / Cr 4070, at
     * SELLING price — postBundleChargeEntry()) is skipped for an
     * insurance-paid case, whose doctor fee is a fixed cash amount (Dr 5130 /
     * Cr 1010, see AutoPostInsuranceDoctorCashPaymentAction) that never
     * touches 2010 and is never supplies-adjusted; an insurance case's
     * consumption is tagged CC-INS (guide §2.5).
     *
     * @param  array<array{inventory_item_id: string, qty: float}>  $selectedItems
     *                                                                              When provided, only those items are deducted with their given quantities.
     *                                                                              When empty, all bundle items are deducted using bundle defaults × $qty.
     * @param  string|null  $surgeryId  Used to resolve the linked booking's pay method (see $isInsurancePaid()).
     * @param  bool  $deductNoItems  The user explicitly selected no items: charge the bundle price but deduct nothing.
     */
    public function process(string $bundleId, int $qty, string $dept = 'surgery', array $selectedItems = [], ?string $surgeryId = null, bool $deductNoItems = false): array
    {
        $bundle = SupplyBundle::with('items.inventoryItem')->findOrFail($bundleId);

        $inventoryAccountId = $this->accountResolver->id(AccountCode::INVENTORY);
        $department = Department::tryFrom($dept) ?? Department::Surgery;
        $surgery = $surgeryId !== null ? Surgery::with('booking:id,file_no,doctor_id,pay_method')->find($surgeryId) : null;
        $isInsurancePaid = $surgery?->isInsurancePaid() ?? false;
        $costCenter = $isInsurancePaid ? CostCenter::Insurance : CostCenter::forDepartment($department, CostCenter::Surgery);
        $reference = $surgery?->booking?->file_no ?? $bundle->name;

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
        $permit = $this->createStockPermit($bundle, $qty, $dept, $selectedMap, $deductNoItems);

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id || $deductNoItems) {
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
                $expenseId = $this->accountResolver->id(AccountCode::consumptionCostCode($department, $category));

                $this->journalService->record([
                    'date' => now()->toDateString(),
                    'description' => "بند: {$item->item_name} — {$bundle->name}",
                    'debit_account_id' => $expenseId,
                    'credit_account_id' => $inventoryAccountId,
                    'amount' => $cost,
                    'source' => JournalSource::SUPPLIES_USED,
                    'reference' => $reference,
                    'idempotency_key' => "bundle_supply_item:{$permit->id}:{$item->inventory_item_id}",
                    'cost_center' => $costCenter,
                ]);
            }
        }

        if (! $isInsurancePaid) {
            $this->postBundleChargeEntry($bundle, $qty, $costCenter, $permit->id, $surgery, $reference);
        }

        return [
            'bundle_id' => $bundle->id,
            'permit_id' => $permit->id,
            'inventory_item_id' => '',
            'name' => $bundle->name,
            'qty' => $qty,
            'unit_cost' => (float) $bundle->price,
            'total' => (float) $bundle->price * $qty,
            'is_bundle' => true,
        ];
    }

    /**
     * Dr the doctor's payable sub-ledger (22xx) / Cr 4070 (إيراد بيع مستهلكات للأطباء)
     * Records the bundle price charged against the doctor's dues at selling
     * price — guide §1.4 / §2.7: revenue to the center (the spread over
     * purchase cost is the center's supplies profit), NOT a patient sale
     * (4210) and NOT a contra-expense (5115 is retired).
     */
    private function postBundleChargeEntry(SupplyBundle $bundle, int $qty, CostCenter $costCenter, string $permitId, ?Surgery $surgery, string $reference): void
    {
        $bundlePrice = round((float) $bundle->price * $qty, 2);
        if ($bundlePrice <= 0) {
            return;
        }

        $doctorId = $surgery?->surgeon_id ?? $surgery?->booking?->doctor_id;

        if (! $doctorId) {
            throw ValidationException::withMessages([
                'bundles' => 'لا يمكن تحميل المستهلكات على الطبيب: الحالة غير مرتبطة بطبيب.',
            ]);
        }

        $doctorPayableId = $this->subledgers->forDoctor($doctorId);
        $revenueId = $this->accountResolver->id(AccountCode::SUPPLIES_SALE_REVENUE);

        $this->journalService->record([
            'date' => now()->toDateString(),
            'description' => "تحميل مستهلكات على الطبيب بسعر البيع: {$bundle->name} × {$qty}",
            'debit_account_id' => $doctorPayableId,
            'credit_account_id' => $revenueId,
            'amount' => $bundlePrice,
            'source' => JournalSource::SUPPLIES_USED,
            'reference' => $reference,
            'idempotency_key' => "bundle_supply_charge:{$permitId}",
            'cost_center' => $costCenter,
        ]);
    }

    private function createStockPermit(SupplyBundle $bundle, int $qty, string $dept, array $selectedMap = [], bool $deductNoItems = false): StockPermit
    {
        $permit = $this->createPermitWithRetry($bundle, $dept);

        $hasSelection = ! empty($selectedMap);

        foreach ($bundle->items as $item) {
            if (! $item->inventory_item_id || $deductNoItems) {
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

    private function createPermitWithRetry(SupplyBundle $bundle, string $dept): StockPermit
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return StockPermit::create([
                    'permit_no' => $this->generatePermitNo(),
                    'type' => PermitType::Out,
                    'department' => $dept,
                    'reason' => "استخدام بند: {$bundle->name}",
                    'created_by' => auth()->id(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::MAX_ATTEMPTS || ! str_contains($e->getMessage(), 'permit_no')) {
                    throw ValidationException::withMessages([
                        'items' => 'تعذر إصدار رقم الإذن بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
                    ]);
                }
            }
        }

        throw ValidationException::withMessages([
            'items' => 'تعذر إصدار رقم الإذن بسبب طلب متزامن، يرجى المحاولة مرة أخرى.',
        ]);
    }

    private function generatePermitNo(): string
    {
        $prefix = 'OUT-'.date('Y').'-';

        $last = StockPermit::where('type', PermitType::Out->value)
            ->where('permit_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('permit_no')
            ->value('permit_no');

        $seq = $last ? ((int) substr($last, -5) + 1) : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
