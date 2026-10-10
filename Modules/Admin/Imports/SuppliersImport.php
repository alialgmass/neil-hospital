<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Inventory\Models\Supplier;

class SuppliersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['الاسم'] ?? $row['name'] ?? ''));

            if (empty($name)) {
                $this->skipped++;

                continue;
            }

            $data = [
                'contact' => $row['المسؤول'] ?? $row['contact'] ?? null,
                'type' => $row['النوع'] ?? $row['type'] ?? null,
                'phone' => $row['الهاتف'] ?? $row['phone'] ?? null,
                'email' => $row['البريد الإلكتروني'] ?? $row['email'] ?? null,
                'address' => $row['العنوان'] ?? $row['address'] ?? null,
                'tax_no' => $row['الرقم الضريبي'] ?? $row['tax_no'] ?? null,
                'terms' => $row['شروط الدفع'] ?? $row['terms'] ?? null,
                'is_active' => in_array(trim((string) ($row['الحالة'] ?? $row['is_active'] ?? 'active')), ['نشط', 'active', 'true', '1', 'yes'], true),
            ];

            $existing = Supplier::where('name', $name)->first();

            $isActive = $data['is_active'];
            unset($data['is_active']);

            if ($existing) {
                $existing->fill(collect($data)->filter()->toArray());
                $existing->is_active = $isActive;
                $existing->save();
                $this->updated++;
            } else {
                Supplier::create(array_merge(['name' => $name, 'is_active' => $isActive], collect($data)->filter()->toArray()));
                $this->created++;
            }
        }
    }
}
