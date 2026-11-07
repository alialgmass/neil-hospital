<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Booking\Models\Booking;
use Modules\Labs\Models\DiagnosticResult;

class LabsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $fileNo = trim((string) ($row['رقم الملف'] ?? $row['file_no'] ?? ''));

            if (empty($fileNo)) {
                $this->skipped++;

                continue;
            }

            $booking = Booking::where('file_no', $fileNo)->first();

            if (! $booking) {
                $this->skipped++;

                continue;
            }

            DiagnosticResult::create([
                'booking_id' => $booking->id,
                'test_name' => $row['اسم الفحص'] ?? $row['test_name'] ?? '',
                'eye' => $row['العين'] ?? $row['eye'] ?? null,
                'result_text' => $row['النتيجة'] ?? $row['result_text'] ?? null,
                'doctor_notes' => $row['ملاحظات الطبيب'] ?? $row['doctor_notes'] ?? null,
                'recorded_at' => $row['التاريخ'] ?? $row['recorded_at'] ?? now(),
            ]);

            $this->created++;
        }
    }
}
