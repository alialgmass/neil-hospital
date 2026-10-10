<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Inventory\Models\Service;

class ServicesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? $row['الاسم'] ?? ''));
            $dept = trim((string) ($row['dept'] ?? $row['القسم'] ?? ''));

            if (empty($name) || ! in_array($dept, ['clinic', 'labs', 'surgery', 'lasik', 'laser'], true)) {
                $this->skipped++;

                continue;
            }

            $centerVal = (float) ($row['center_val'] ?? $row['نسبة_المركز'] ?? 0);
            $centerType = $centerVal > 1 ? 'pct' : 'fixed';

            $code = mb_strtoupper(trim((string) ($row['code'] ?? $row['الكود'] ?? '')));

            $existing = ($code !== '' ? Service::where('code', $code)->first() : null)
                ?? Service::where('name', $name)->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'dept' => $dept,
                    'price' => (float) ($row['price'] ?? $row['السعر'] ?? 0),
                    'ins_price' => (float) ($row['ins_price'] ?? 0),
                    'center_type' => $centerType,
                    'center_val' => $centerVal,
                ]);
                $this->updated++;
            } else {
                Service::create([
                    'code' => $code !== '' ? $code : null,
                    'name' => $name,
                    'dept' => $dept,
                    'price' => (float) ($row['price'] ?? $row['السعر'] ?? 0),
                    'ins_price' => (float) ($row['ins_price'] ?? 0),
                    'center_type' => $centerType,
                    'center_val' => $centerVal,
                    'status' => 'active',
                ]);
                $this->created++;
            }
        }
    }
}
