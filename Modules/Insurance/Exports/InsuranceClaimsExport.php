<?php

namespace Modules\Insurance\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Insurance\Models\InsuranceClaim;

class InsuranceClaimsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $claims) {}

    public function collection(): Collection
    {
        return $this->claims;
    }

    public function headings(): array
    {
        return [
            'رقم الملف', 'اسم المريض', 'الخدمة', 'تاريخ المطالبة',
            'قيمة الفاتورة', 'الخصم', 'نصيب التأمين', 'نصيب المريض',
            'المبلغ المعتمد', 'المبلغ المسدد', 'الحالة',
        ];
    }

    public function map($claim): array
    {
        /** @var InsuranceClaim $claim */
        return [
            $claim->file_no,
            $claim->patient_name,
            $claim->service_name,
            optional($claim->claim_date)->format('Y-m-d'),
            $claim->invoice_amount,
            $claim->discount,
            $claim->insurance_share,
            $claim->patient_share,
            $claim->approved_amount,
            $claim->paid_amount,
            $claim->status->label(),
        ];
    }
}
