<?php

namespace Modules\Admin\Services;

use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Admin\Exports\ModuleTemplateExport;
use Modules\Admin\Imports\DoctorsImport;
use Modules\Admin\Imports\HrImport;
use Modules\Admin\Imports\InsuranceCompanyImport;
use Modules\Admin\Imports\InventoryItemImport;
use Modules\Admin\Imports\PriceListImport;
use Modules\Admin\Imports\PurchasesImport;
use Modules\Admin\Imports\SuppliersImport;

/**
 * Per-module Excel import registry used by the "import + download template"
 * buttons rendered on each module's own list page. Keeps headings and import
 * class resolution in one place, decoupled from the SystemModule enum so each
 * module routes to its own endpoint with its own permission.
 */
class ModuleImportRegistry
{
    private const MODULES = [
        'inventory' => [
            'headings' => ['الكود', 'الصنف', 'الفئة', 'الوحدة', 'الحد الأدنى', 'سعر الشراء', 'سعر البيع'],
            'import' => InventoryItemImport::class,
            'title' => 'المخزون',
        ],
        'employees' => [
            'headings' => ['الرقم', 'الاسم', 'القسم', 'الوظيفة', 'الهاتف', 'تاريخ التعيين', 'الراتب الأساسي', 'البدلات', 'نوع العقد', 'الحالة'],
            'import' => HrImport::class,
            'title' => 'الموظفون',
        ],
        'doctors' => [
            'headings' => ['الاسم', 'التخصص', 'الهاتف', 'نوع الأتعاب', 'قيمة الأتعاب', 'نشط'],
            'import' => DoctorsImport::class,
            'title' => 'الأطباء',
        ],
        'suppliers' => [
            'headings' => ['الاسم', 'المسؤول', 'النوع', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'الرقم الضريبي', 'شروط الدفع', 'الحالة'],
            'import' => SuppliersImport::class,
            'title' => 'الموردون',
        ],
        'purchases' => [
            'headings' => ['رقم الفاتورة', 'المورد', 'التاريخ', 'التخفيض', 'المدفوع', 'اسم الصنف', 'الكمية', 'سعر الوحدة', 'ملاحظات'],
            'import' => PurchasesImport::class,
            'title' => 'المشتريات',
        ],
        'insurance' => [
            'headings' => ['الاسم', 'الكود', 'الهاتف', 'العنوان', 'رقم العقد', 'نسبة التغطية', 'نسبة الخصم', 'جهة الاتصال', 'البريد الإلكتروني', 'الحالة'],
            'import' => InsuranceCompanyImport::class,
            'title' => 'شركات التأمين',
        ],
        'price-lists' => [
            'headings' => ['اسم القائمة', 'النوع', 'شركة التأمين', 'نسبة التغطية', 'نسبة الخصم', 'الخدمة', 'السعر', 'الحالة'],
            'import' => PriceListImport::class,
            'title' => 'قوائم الأسعار',
        ],
    ];

    public static function supported(string $module): bool
    {
        return isset(self::MODULES[$module]);
    }

    public static function title(string $module): string
    {
        return self::MODULES[$module]['title'];
    }

    public static function template(string $module): ModuleTemplateExport
    {
        return new ModuleTemplateExport(
            self::MODULES[$module]['headings'],
            self::MODULES[$module]['title'],
        );
    }

    public static function importer(string $module): ToCollection
    {
        $class = self::MODULES[$module]['import'];

        return app($class);
    }
}
