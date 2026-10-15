<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Booking\Models\Booking;

class BookingImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function __construct(
        private readonly string $dept,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $fileNo = trim((string) ($row['رقم الملف'] ?? $row['file_no'] ?? ''));

            if (empty($fileNo)) {
                $this->skipped++;

                continue;
            }

            $data = [
                'patient_name' => $row['اسم المريض'] ?? $row['patient_name'] ?? null,
                'dept' => $row['القسم'] ?? $this->dept,
                'service_name' => $row['الخدمة'] ?? $row['service_name'] ?? null,
                'visit_date' => $row['تاريخ الزيارة'] ?? $row['visit_date'] ?? null,
                'price' => (float) ($row['السعر'] ?? $row['price'] ?? 0),
                'paid_amount' => (float) ($row['المدفوع'] ?? $row['paid_amount'] ?? 0),
                'pay_method' => $row['طريقة الدفع'] ?? $row['pay_method'] ?? 'cash',
                'pay_status' => $row['حالة الدفع'] ?? $row['pay_status'] ?? 'unpaid',
                'status' => $row['الحالة'] ?? $row['status'] ?? 'waiting',
            ];

            $existing = Booking::where('file_no', $fileNo)->first();

            if ($existing) {
                $existing->update(collect($data)->filter()->toArray());
                $this->updated++;
            } else {
                $this->skipped++;
            }
        }
    }
}
