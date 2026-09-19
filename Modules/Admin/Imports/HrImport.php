<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\HR\Models\Employee;

class HrImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $employeeNo = trim((string) ($row['الرقم'] ?? $row['employee_no'] ?? ''));
            $name = trim((string) ($row['الاسم'] ?? $row['name'] ?? ''));

            if (empty($name)) {
                $this->skipped++;

                continue;
            }

            $data = [
                'employee_no' => $employeeNo,
                'name' => $name,
                'dept' => $row['القسم'] ?? $row['dept'] ?? null,
                'position' => $row['الوظيفة'] ?? $row['position'] ?? null,
                'phone' => $row['الهاتف'] ?? $row['phone'] ?? null,
                'hire_date' => $row['تاريخ التعيين'] ?? $row['hire_date'] ?? null,
                'base_salary' => (float) ($row['الراتب الأساسي'] ?? $row['base_salary'] ?? 0),
                'allowances' => (float) ($row['البدلات'] ?? $row['allowances'] ?? 0),
                'contract_type' => $row['نوع العقد'] ?? $row['contract_type'] ?? null,
                'status' => $row['الحالة'] ?? $row['status'] ?? 'active',
            ];

            $existing = $employeeNo ? Employee::where('employee_no', $employeeNo)->first() : null;

            if ($existing) {
                $existing->update(collect($data)->filter()->toArray());
                $this->updated++;
            } else {
                Employee::create(collect($data)->filter()->toArray());
                $this->created++;
            }
        }
    }
}
