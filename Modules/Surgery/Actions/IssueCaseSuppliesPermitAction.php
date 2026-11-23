<?php

namespace Modules\Surgery\Actions;

use Modules\Inventory\Actions\IssueStockPermitAction;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Surgery\Models\Surgery;

/**
 * Issues the stock-issue permit (إذن صرف) behind individual supplies added to
 * a surgery / lasik case: stock is deducted and the consumption entry
 * (Dr department supplies cost / Cr 1051) is posted at the item's purchase
 * cost, exactly like any other issue permit.
 */
class IssueCaseSuppliesPermitAction
{
    public function __construct(private readonly IssueStockPermitAction $issuePermit) {}

    /**
     * @param  array<int, array{inventory_item_id: string, name: string, qty: float|int|string}>  $lines
     */
    public function execute(Surgery $surgery, array $lines): StockPermit
    {
        $surgery->loadMissing('booking:id,file_no,patient_name');

        $items = array_map(function (array $line): array {
            $inventoryItem = InventoryItem::findOrFail($line['inventory_item_id']);

            return [
                'item_id' => $inventoryItem->id,
                'item_name' => $line['name'] !== '' ? $line['name'] : $inventoryItem->name,
                'qty' => (float) $line['qty'],
                'unit_cost' => (float) $inventoryItem->unit_cost,
            ];
        }, $lines);

        $fileNo = $surgery->booking?->file_no;

        return $this->issuePermit->execute([
            'department' => $surgery->dept->value,
            'reason' => 'مستلزمات حالة'.($fileNo ? ": {$fileNo}" : ''),
            'notes' => $surgery->booking?->patient_name,
        ], $items);
    }
}
