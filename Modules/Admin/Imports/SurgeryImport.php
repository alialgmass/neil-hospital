<?php

namespace Modules\Admin\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\Surgery\Models\Surgery;

class SurgeryImport implements ToCollection, WithHeadingRow
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

            $surgeonName = trim((string) ($row['الجراح'] ?? $row['surgeon'] ?? ''));
            $surgeon = $surgeonName ? Doctor::where('name', $surgeonName)->first() : null;

            Surgery::create([
                'booking_id' => $booking->id,
                'surgeon_id' => $surgeon?->id,
                'procedure' => $row['نوع العملية'] ?? $row['procedure'] ?? '',
                'eye' => $row['العين'] ?? $row['eye'] ?? null,
                'anaesthesia' => $row['التخدير'] ?? $row['anaesthesia'] ?? null,
                'scheduled_at' => $row['التاريخ المقرر'] ?? $row['scheduled_at'] ?? null,
                'status' => $row['الحالة'] ?? $row['status'] ?? 'scheduled',
            ]);

            $this->created++;
        }
    }
}
