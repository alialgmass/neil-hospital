<?php

namespace Modules\Admin\Services;

use Modules\Accounting\Models\JournalEntry;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Exports\ModuleExport;
use Modules\Booking\Models\Booking;
use Modules\Doctor\Models\Doctor;
use Modules\HR\Models\Employee;
use Modules\Inventory\Models\InventoryItem;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Surgery\Models\Surgery;

/**
 * Builds a per-module Excel workbook containing that module's core tables.
 */
class ModuleExportService
{
    public function export(SystemModule $module): ModuleExport
    {
        return match ($module) {
            SystemModule::Booking => $this->booking(),
            SystemModule::Clinic => $this->deptBookings('clinic'),
            SystemModule::Labs => $this->labs(),
            SystemModule::Surgery => $this->surgery(),
            SystemModule::Lasik => $this->deptBookings('lasik'),
            SystemModule::Laser => $this->deptBookings('laser'),
            SystemModule::Pentacam => $this->deptBookings('pentacam'),
            SystemModule::Doctors => $this->doctors(),
            SystemModule::Accounting => $this->accounting(),
            SystemModule::Inventory => $this->inventory(),
            SystemModule::Hr => $this->hr(),
            SystemModule::Reports => $this->reports(),
        };
    }

    private function booking(): ModuleExport
    {
        $headings = [
            'رقم الملف', 'اسم المريض', 'القسم', 'الخدمة', 'الطبيب',
            'تاريخ الزيارة', 'الوقت', 'السعر', 'المدفوع', 'الإجمالي المتبقي', 'طريقة الدفع', 'حالة الدفع', 'الحالة',
        ];

        return new ModuleExport(
            Booking::with(['doctor', 'service'])->latest('visit_date'),
            $headings,
            'الحجز',
            fn (Booking $b) => [
                $b->file_no,
                $b->patient_name,
                $b->dept?->value,
                $b->service_name,
                $b->doctor?->name ?? '—',
                $b->visit_date?->toDateString(),
                $b->visit_time,
                (string) $b->price,
                (string) $b->paid_amount,
                (string) $b->remaining_amount,
                $b->pay_method?->value,
                $b->pay_status?->value,
                (string) $b->status,
            ],
        );
    }

    private function deptBookings(string $dept): ModuleExport
    {
        $headings = [
            'رقم الملف', 'اسم المريض', 'الخدمة', 'الطبيب',
            'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة',
        ];

        return new ModuleExport(
            Booking::with(['doctor', 'service'])->where('dept', $dept)->latest('visit_date'),
            $headings,
            ucfirst($dept),
            fn (Booking $b) => [
                $b->file_no,
                $b->patient_name,
                $b->service_name,
                $b->doctor?->name ?? '—',
                $b->visit_date?->toDateString(),
                (string) $b->price,
                (string) $b->paid_amount,
                $b->pay_method?->value,
                $b->pay_status?->value,
                (string) $b->status,
            ],
        );
    }

    private function labs(): ModuleExport
    {
        $headings = [
            'رقم الملف', 'اسم المريض', 'اسم الفحص', 'العين', 'النتيجة',
            'ملاحظات الطبيب', 'التاريخ',
        ];

        return new ModuleExport(
            DiagnosticResult::with(['booking'])->latest('recorded_at'),
            $headings,
            'الفحوصات',
            fn (DiagnosticResult $r) => [
                $r->booking?->file_no ?? '—',
                $r->booking?->patient_name ?? '—',
                $r->test_name,
                $r->eye,
                $r->result_text,
                $r->doctor_notes,
                $r->recorded_at?->toDateTimeString(),
            ],
        );
    }

    private function surgery(): ModuleExport
    {
        $headings = [
            'رقم الملف', 'اسم المريض', 'الجراح', 'نوع العملية', 'العين',
            'التخدير', 'التاريخ المقرر', 'الحالة', 'إجمالي المستلزمات',
        ];

        return new ModuleExport(
            Surgery::with(['booking', 'surgeon'])->latest('scheduled_at'),
            $headings,
            'العمليات',
            fn (Surgery $s) => [
                $s->booking?->file_no ?? '—',
                $s->booking?->patient_name ?? '—',
                $s->surgeon?->name ?? '—',
                $s->procedure,
                $s->eye?->value,
                $s->anaesthesia?->value,
                $s->scheduled_at?->toDateTimeString(),
                (string) $s->status,
                (string) $s->supply_total,
            ],
        );
    }

    private function doctors(): ModuleExport
    {
        $headings = [
            'الاسم', 'التخصص', 'الهاتف', 'نوع الأتعاب', 'قيمة الأتعاب', 'نشط',
        ];

        return new ModuleExport(
            Doctor::query()->orderBy('name'),
            $headings,
            'الأطباء',
            fn (Doctor $d) => [
                $d->name,
                $d->specialty,
                $d->phone,
                $d->fee_type?->value,
                (string) $d->fee_value,
                $d->is_active ? 'نعم' : 'لا',
            ],
        );
    }

    private function accounting(): ModuleExport
    {
        $headings = [
            'التاريخ', 'الوصف', 'حساب مدين', 'حساب دائن', 'المبلغ',
            'المرجع', 'المصدر', 'مركز التكلفة',
        ];

        return new ModuleExport(
            JournalEntry::with(['debitAccount', 'creditAccount'])->latest('date'),
            $headings,
            'المالية والمحاسبة',
            fn (JournalEntry $j) => [
                $j->date?->toDateString(),
                $j->description,
                $j->debitAccount?->name,
                $j->creditAccount?->name,
                (string) $j->amount,
                $j->reference,
                $j->source?->value,
                $j->cost_center?->value,
            ],
        );
    }

    private function inventory(): ModuleExport
    {
        $headings = [
            'الكود', 'الصنف', 'الفئة', 'الوحدة', 'الكمية', 'الحد الأدنى',
            'سعر الشراء', 'سعر البيع', 'المورد', 'تاريخ الانتهاء', 'الموقع',
        ];

        return new ModuleExport(
            InventoryItem::with(['supplier'])->orderBy('name'),
            $headings,
            'المخزن',
            fn (InventoryItem $item) => [
                $item->code,
                $item->name,
                $item->category_label,
                $item->unit_label,
                (string) $item->quantity,
                (string) $item->min_quantity,
                (string) $item->unit_cost,
                (string) $item->sell_price,
                $item->supplier?->name ?? '—',
                $item->expiry_date?->toDateString(),
                $item->location,
            ],
        );
    }

    private function hr(): ModuleExport
    {
        $headings = [
            'الرقم', 'الاسم', 'القسم', 'الوظيفة', 'الهاتف', 'تاريخ التعيين',
            'الراتب الأساسي', 'البدلات', 'نوع العقد', 'الحالة',
        ];

        return new ModuleExport(
            Employee::query()->orderBy('name'),
            $headings,
            'الموارد البشرية',
            fn (Employee $e) => [
                $e->employee_no,
                $e->name,
                $e->dept,
                $e->position,
                $e->phone,
                $e->hire_date?->toDateString(),
                (string) $e->base_salary,
                (string) $e->allowances,
                $e->contract_type?->value,
                $e->status?->value,
            ],
        );
    }

    private function reports(): ModuleExport
    {
        $headings = [
            'رقم الملف', 'اسم المريض', 'القسم', 'التاريخ', 'السعر', 'الحالة',
        ];

        return new ModuleExport(
            Booking::with(['doctor', 'service'])->latest('visit_date'),
            $headings,
            'التقارير والأرشيف',
            fn (Booking $b) => [
                $b->file_no,
                $b->patient_name,
                $b->dept?->value,
                $b->visit_date?->toDateString(),
                (string) $b->price,
                (string) $b->status,
            ],
        );
    }
}
