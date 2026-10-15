<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\PurchaseInvoiceService;

class PurchasesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function __construct(
        private readonly PurchaseInvoiceService $purchaseService,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $itemName = trim((string) ($row['اسم الصنف'] ?? $row['item_name'] ?? ''));

            if (empty($itemName)) {
                $this->skipped++;

                continue;
            }

            $invoiceNo = trim((string) ($row['رقم الفاتورة'] ?? $row['invoice_no'] ?? ''));

            if ($invoiceNo === '') {
                $this->skipped++;

                continue;
            }

            if (PurchaseInvoice::where('invoice_no', $invoiceNo)->exists()) {
                $this->skipped++;

                continue;
            }

            $supplierName = trim((string) ($row['المورد'] ?? $row['supplier'] ?? ''));
            $supplier = $supplierName !== '' ? Supplier::where('name', $supplierName)->first() : null;

            $data = [
                'invoice_no' => $invoiceNo,
                'supplier_id' => $supplier?->id,
                'invoice_date' => $row['التاريخ'] ?? $row['invoice_date'] ?? now()->toDateString(),
                'discount' => (float) ($row['التخفيض'] ?? $row['discount'] ?? 0),
                'paid_amount' => (float) ($row['المدفوع'] ?? $row['paid_amount'] ?? 0),
                'notes' => $row['ملاحظات'] ?? $row['notes'] ?? null,
            ];

            $item = [
                'item_name' => $itemName,
                'qty' => (float) ($row['الكمية'] ?? $row['qty'] ?? 1),
                'unit_cost' => (float) ($row['سعر الوحدة'] ?? $row['unit_cost'] ?? 0),
            ];

            $inventoryItem = InventoryItem::where('name', $itemName)->first();

            if ($inventoryItem) {
                $item['item_id'] = $inventoryItem->id;
            }

            $this->purchaseService->create($data, [$item]);
            $this->created++;
        }
    }
}
