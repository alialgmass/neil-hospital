<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Doctor\Models\Doctor;

class DoctorsImport implements ToCollection, WithHeadingRow
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
                'specialty' => $row['التخصص'] ?? $row['specialty'] ?? null,
                'phone' => $row['الهاتف'] ?? $row['phone'] ?? null,
                'fee_type' => $row['نوع الأتعاب'] ?? $row['fee_type'] ?? null,
                'fee_value' => (float) ($row['قيمة الأتعاب'] ?? $row['fee_value'] ?? 0),
                'is_active' => in_array(trim((string) ($row['نشط'] ?? $row['is_active'] ?? 'نعم')), ['نعم', 'true', '1', 'yes'], true),
            ];

            $existing = Doctor::where('name', $name)->first();

            if ($existing) {
                $existing->update(collect($data)->filter()->toArray());
                $this->updated++;
            } else {
                Doctor::create(array_merge(['name' => $name], collect($data)->filter()->toArray()));
                $this->created++;
            }
        }
    }
}
