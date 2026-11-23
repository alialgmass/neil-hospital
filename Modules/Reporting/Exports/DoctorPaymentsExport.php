<?php

namespace Modules\Reporting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DoctorPaymentsExport implements FromCollection, WithHeadings, WithMapping
{
    private const METHOD_LABELS = [
        'cash' => 'كاش',
        'transfer' => 'تحويل بنكي',
    ];

    public function __construct(private readonly array $data) {}

    public function collection(): Collection
    {
        return collect($this->data['rows']);
    }

    public function headings(): array
    {
        return ['الطبيب', 'المبلغ', 'طريقة الدفع', 'فترة الاستحقاق من', 'فترة الاستحقاق إلى', 'تاريخ الدفع', 'بواسطة', 'ملاحظات'];
    }

    public function map($row): array
    {
        return [
            $row->doctor_name,
            $row->amount,
            self::METHOD_LABELS[$row->method] ?? $row->method,
            $row->period_from,
            $row->period_to,
            $row->paid_at,
            $row->paid_by_name,
            $row->notes,
        ];
    }
}
