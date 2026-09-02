<?php

namespace Modules\Booking\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $bookings) {}

    public function collection(): Collection
    {
        return $this->bookings;
    }

    public function headings(): array
    {
        return ['رقم الملف', 'المريض', 'الهاتف', 'القسم', 'الخدمة', 'الطبيب', 'التاريخ', 'الوقت', 'جانب العين', 'السعر', 'الخصم', 'التأمين', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'];
    }

    public function map($booking): array
    {
        return [
            $booking->file_no,
            $booking->patient_name,
            $booking->patient_phone ?? '—',
            $booking->dept->value,
            $booking->service_name ?? '—',
            $booking->doctor?->name ?? '—',
            $booking->visit_date->toDateString(),
            $booking->visit_time ?? '—',
            $booking->eye_side?->value ?? '—',
            (float) $booking->price,
            (float) ($booking->discount ?? 0),
            (float) ($booking->ins_amount ?? 0),
            (float) $booking->paid_amount,
            $booking->pay_method?->value ?? '—',
            $booking->pay_status?->value ?? '—',
            (string) $booking->status,
        ];
    }
}
