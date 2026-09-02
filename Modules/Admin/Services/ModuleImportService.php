<?php

namespace Modules\Admin\Services;

use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Exports\ModuleTemplateExport;
use Modules\Admin\Imports\AccountingImport;
use Modules\Admin\Imports\BookingImport;
use Modules\Admin\Imports\DoctorsImport;
use Modules\Admin\Imports\HrImport;
use Modules\Admin\Imports\InsuranceImport;
use Modules\Admin\Imports\InventoryItemImport;
use Modules\Admin\Imports\LabsImport;
use Modules\Admin\Imports\SurgeryImport;

class ModuleImportService
{
    private const CONFIG = [
        'booking' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'القسم', 'الخدمة', 'الطبيب', 'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'],
            'has_import' => true,
        ],
        'clinic' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'الخدمة', 'الطبيب', 'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'],
            'has_import' => true,
        ],
        'labs' => [
            'headings' => ['رقم الملف', 'اسم الفحص', 'العين', 'النتيجة', 'ملاحظات الطبيب', 'التاريخ'],
            'has_import' => true,
        ],
        'surgery' => [
            'headings' => ['رقم الملف', 'الجراح', 'نوع العملية', 'العين', 'التخدير', 'التاريخ المقرر', 'الحالة'],
            'has_import' => true,
        ],
        'lasik' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'الخدمة', 'الطبيب', 'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'],
            'has_import' => true,
        ],
        'laser' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'الخدمة', 'الطبيب', 'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'],
            'has_import' => true,
        ],
        'pentacam' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'الخدمة', 'الطبيب', 'تاريخ الزيارة', 'السعر', 'المدفوع', 'طريقة الدفع', 'حالة الدفع', 'الحالة'],
            'has_import' => true,
        ],
        'doctors' => [
            'headings' => ['الاسم', 'التخصص', 'الهاتف', 'نوع الأتعاب', 'قيمة الأتعاب', 'نشط'],
            'has_import' => true,
        ],
        'accounting' => [
            'headings' => ['التاريخ', 'الوصف', 'حساب مدين', 'حساب دائن', 'المبلغ', 'المرجع'],
            'has_import' => true,
        ],
        'inventory' => [
            'headings' => ['الكود', 'الصنف', 'الفئة', 'الوحدة', 'الحد الأدنى', 'سعر الشراء', 'سعر البيع'],
            'has_import' => true,
        ],
        'hr' => [
            'headings' => ['الرقم', 'الاسم', 'القسم', 'الوظيفة', 'الهاتف', 'تاريخ التعيين', 'الراتب الأساسي', 'البدلات', 'نوع العقد', 'الحالة'],
            'has_import' => true,
        ],
        'reports' => [
            'headings' => ['رقم الملف', 'اسم المريض', 'القسم', 'التاريخ', 'السعر', 'الحالة'],
            'has_import' => false,
        ],
    ];

    public function headings(SystemModule $module): array
    {
        return self::CONFIG[$module->value]['headings'];
    }

    public function hasImport(SystemModule $module): bool
    {
        return self::CONFIG[$module->value]['has_import'];
    }

    public function template(SystemModule $module): ModuleTemplateExport
    {
        return new ModuleTemplateExport(
            $this->headings($module),
            $module->label(),
        );
    }

    /**
     * Resolve the import class for a module.
     */
    public function resolveImport(SystemModule $module): ToCollection
    {
        return match ($module) {
            SystemModule::Booking => new BookingImport('booking'),
            SystemModule::Clinic => new BookingImport('clinic'),
            SystemModule::Lasik => new BookingImport('lasik'),
            SystemModule::Laser => new BookingImport('laser'),
            SystemModule::Pentacam => new BookingImport('pentacam'),
            SystemModule::Labs => new LabsImport,
            SystemModule::Surgery => new SurgeryImport,
            SystemModule::Doctors => new DoctorsImport,
            SystemModule::Accounting => new AccountingImport,
            SystemModule::Inventory => new InventoryItemImport,
            SystemModule::Hr => new HrImport,
            SystemModule::Insurance => new InsuranceImport,
            default => throw new \InvalidArgumentException("No import available for module: {$module->value}"),
        };
    }
}
