<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\Service;
use Modules\Inventory\Models\Supplier;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleImportRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventory.write', 'hr.manage', 'doctors.write', 'insurance.write'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo(['inventory.write', 'hr.manage', 'doctors.write', 'insurance.write']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_template_download_returns_xlsx_for_all_registered_modules(): void
    {
        foreach ([
            '/inventory/import-template',
            '/employees/import-template',
            '/doctors/import-template',
            '/suppliers/import-template',
            '/purchases/import-template',
            '/insurance/companies/import-template',
            '/insurance/price-lists/import-template',
        ] as $url) {
            $response = $this->actingAs($this->user)->get($url);

            $response->assertOk();
            $this->assertStringStartsWith(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $response->headers->get('Content-Type'),
            );
        }
    }

    public function test_template_download_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/suppliers/import-template')->assertForbidden();
    }

    public function test_import_requires_file(): void
    {
        $this->actingAs($this->user)->post('/suppliers/import')->assertSessionHasErrors('file');
    }

    public function test_suppliers_import_creates_records(): void
    {
        $file = $this->createExcelFromRows([
            ['الاسم' => 'مورد النور', 'المسؤول' => 'أحمد', 'النوع' => 'أدوية', 'الهاتف' => '0100', 'شروط الدفع' => 'آجل', 'الحالة' => 'نشط'],
            ['الاسم' => 'مورد الشمس', 'النوع' => 'مستلزمات', 'الهاتف' => '0101', 'الحالة' => 'غير نشط'],
        ]);

        $response = $this->actingAs($this->user)->post('/suppliers/import', ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('suppliers', ['name' => 'مورد النور', 'type' => 'أدوية']);
        $this->assertDatabaseHas('suppliers', ['name' => 'مورد الشمس', 'is_active' => false]);
    }

    public function test_suppliers_import_updates_existing_records(): void
    {
        Supplier::create(['name' => 'مورد النور', 'type' => 'قديم']);

        $file = $this->createExcelFromRows([
            ['الاسم' => 'مورد النور', 'النوع' => 'جديد', 'الهاتف' => '0100', 'الحالة' => 'نشط'],
        ]);

        $this->actingAs($this->user)->post('/suppliers/import', ['file' => $file]);

        $result = session('importResult');
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals('جديد', Supplier::where('name', 'مورد النور')->first()->type);
    }

    public function test_insurance_companies_import_creates_records(): void
    {
        $file = $this->createExcelFromRows([
            ['الاسم' => 'التأمين الأهلي', 'الكود' => 'INS-1', 'نسبة التغطية' => '80', 'نسبة الخصم' => '20', 'الحالة' => 'نشط'],
        ]);

        $response = $this->actingAs($this->user)->post('/insurance/companies/import', ['file' => $file]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(1, $result['created']);

        $this->assertDatabaseHas('insurance_companies', ['name' => 'التأمين الأهلي', 'code' => 'INS-1']);
        $this->assertEquals(80.0, InsuranceCompany::where('name', 'التأمين الأهلي')->first()->coverage_pct);
    }

    public function test_purchases_import_creates_invoice_and_item(): void
    {
        $item = InventoryItem::create(['name' => 'أقراص باراسيتامول', 'quantity' => 0]);
        $supplier = Supplier::create(['name' => 'مورد النور']);

        $file = $this->createExcelFromRows([
            ['رقم الفاتورة' => 'PI-1001', 'المورد' => 'مورد النور', 'التاريخ' => '2026-09-01', 'التخفيض' => '10', 'المدفوع' => '0', 'اسم الصنف' => 'أقراص باراسيتامول', 'الكمية' => '5', 'سعر الوحدة' => '20'],
        ]);

        $response = $this->actingAs($this->user)->post('/purchases/import', ['file' => $file]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(1, $result['created']);

        $invoice = PurchaseInvoice::where('invoice_no', 'PI-1001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($supplier->id, $invoice->supplier_id);
        $this->assertEquals(5, $invoice->items()->first()->qty);
        $this->assertEquals(90, (float) $invoice->total); // 5*20 - 10
        $this->assertEquals($item->id, $invoice->items()->first()->item_id);
        $this->assertEquals(5, $item->fresh()->quantity);
    }

    public function test_purchases_import_skips_duplicate_invoice(): void
    {
        PurchaseInvoice::create([
            'invoice_no' => 'PI-2000',
            'invoice_date' => '2026-09-01',
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'remaining' => 100,
        ]);

        $file = $this->createExcelFromRows([
            ['رقم الفاتورة' => 'PI-2000', 'المورد' => '', 'التاريخ' => '2026-09-01', 'اسم الصنف' => 'شيء', 'الكمية' => '1', 'سعر الوحدة' => '10'],
        ]);

        $this->actingAs($this->user)->post('/purchases/import', ['file' => $file]);

        $result = session('importResult');
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['created']);
    }

    public function test_price_lists_import_create_with_items(): void
    {
        $company = InsuranceCompany::create(['name' => 'التأمين الأهلي', 'status' => 'active']);
        Service::create(['name' => 'فحص عيون', 'dept' => 'clinic', 'price' => 500]);
        Service::create(['name' => 'تحليل دم', 'dept' => 'lab', 'price' => 300]);

        $file = $this->createExcelFromRows([
            ['اسم القائمة' => 'قائمة الذهبية', 'النوع' => 'insurance', 'شركة التأمين' => 'التأمين الأهلي', 'نسبة التغطية' => '90', 'نسبة الخصم' => '10', 'الخدمة' => 'فحص عيون', 'السعر' => '450', 'الحالة' => 'نشط'],
            ['اسم القائمة' => 'قائمة الذهبية', 'النوع' => 'insurance', 'شركة التأمين' => 'التأمين الأهلي', 'الخدمة' => 'تحليل دم', 'السعر' => '300', 'الحالة' => 'نشط'],
        ]);

        $response = $this->actingAs($this->user)->post('/insurance/price-lists/import', ['file' => $file]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(1, $result['created']);

        $priceList = PriceList::where('name', 'قائمة الذهبية')->first();
        $this->assertNotNull($priceList);
        $this->assertEquals($company->id, $priceList->ins_company_id);
        $this->assertEquals(90.0, $priceList->ins_coverage);
        $this->assertEquals(2, $priceList->items()->count());
    }

    /**
     * Build an in-memory xlsx from an array of associative rows.
     */
    private function createExcelFromRows(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($rows !== []) {
            $headers = array_keys($rows[0]);
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            }
            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $col => $value) {
                    $sheet->setCellValueByColumnAndRow(array_search($col, $headers) + 1, $rowIndex + 2, $value);
                }
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        return new UploadedFile($tmpFile, 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
