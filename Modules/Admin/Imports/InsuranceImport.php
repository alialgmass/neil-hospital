<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Booking\Models\InsuranceCompany;

class InsuranceImport implements ToCollection, WithHeadingRow
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
                'code' => $row['الكود'] ?? $row['code'] ?? null,
                'phone' => $row['الهاتف'] ?? $row['phone'] ?? null,
                'address' => $row['العنوان'] ?? $row['address'] ?? null,
                'contract_no' => $row['رقم العقد'] ?? $row['contract_no'] ?? null,
                'coverage_pct' => (float) ($row['نسبة التغطية'] ?? $row['coverage_pct'] ?? 0),
                'contact_person' => $row['جهة الاتصال'] ?? $row['contact_person'] ?? null,
                'email' => $row['البريد الإلكتروني'] ?? $row['email'] ?? null,
                'status' => $row['الحالة'] ?? $row['status'] ?? 'active',
            ];

            $existing = InsuranceCompany::where('name', $name)->first();

            if ($existing) {
                $existing->update(collect($data)->filter()->toArray());
                $this->updated++;
            } else {
                InsuranceCompany::create(array_merge(['name' => $name], collect($data)->filter()->toArray()));
                $this->created++;
            }
        }
    }
}
