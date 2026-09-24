<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Admin\Enums\SystemModule;
use Modules\Doctor\Models\Doctor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'users.manage']);
        $role = Role::firstOrCreate(['name' => 'admin']);
        $role->givePermissionTo('users.manage');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_index_lists_all_modules(): void
    {
        $response = $this->actingAs($this->user)->get('/module-imports');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('modules', count(SystemModule::cases()))
        );
    }

    public function test_index_marks_importable_modules(): void
    {
        $response = $this->actingAs($this->user)->get('/module-imports');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('modules.0.hasImport', true) // Booking
            ->where('modules.11.hasImport', false) // Reports
        );
    }

    public function test_index_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/module-imports')->assertForbidden();
    }

    public function test_template_download_returns_xlsx_for_all_modules(): void
    {
        foreach (SystemModule::cases() as $module) {
            $response = $this->actingAs($this->user)->get("/module-imports/{$module->value}/template");

            $response->assertStatus(200);
            $this->assertStringStartsWith(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $response->headers->get('Content-Type'),
            );
        }
    }

    public function test_template_download_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/module-imports/doctors/template')->assertForbidden();
    }

    public function test_import_requires_file(): void
    {
        $response = $this->actingAs($this->user)->post('/module-imports/doctors/import');

        $response->assertSessionHasErrors('file');
    }

    public function test_import_is_blocked_without_permission(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)->post('/module-imports/doctors/import', [
            'file' => $file,
        ])->assertForbidden();
    }

    public function test_doctors_import_creates_new_records(): void
    {
        $file = $this->createExcelFromRows([
            ['الاسم' => 'د. أحمد', 'التخصص' => ' глаз', 'الهاتف' => '0500', 'نوع الأتعاب' => 'fixed', 'قيمة الأتعاب' => '500', 'نشط' => 'نعم'],
            ['الاسم' => 'د. سعيد', 'التخصص' => 'lab', 'الهاتف' => '0501', 'نوع الأتعاب' => 'percentage', 'قيمة الأتعاب' => '30', 'نشط' => 'لا'],
        ]);

        $response = $this->actingAs($this->user)->post('/module-imports/doctors/import', [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('doctors', ['name' => 'د. أحمد']);
        $this->assertDatabaseHas('doctors', ['name' => 'د. سعيد']);
    }

    public function test_doctors_import_updates_existing_records(): void
    {
        Doctor::create([
            'name' => 'د. أحمد',
            'specialty' => 'old',
            'fee_type' => 'fixed',
            'fee_value' => 100,
        ]);

        $file = $this->createExcelFromRows([
            ['الاسم' => 'د. أحمد', 'التخصص' => 'new specialty', 'الهاتف' => '0500', 'نوع الأتعاب' => 'fixed', 'قيمة الأتعاب' => '500', 'نشط' => 'نعم'],
        ]);

        $response = $this->actingAs($this->user)->post('/module-imports/doctors/import', [
            'file' => $file,
        ]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);

        $doctor = Doctor::where('name', 'د. أحمد')->first();
        $this->assertEquals('new specialty', $doctor->specialty);
        $this->assertEquals(500, $doctor->fee_value);
    }

    public function test_hr_import_creates_new_records(): void
    {
        $file = $this->createExcelFromRows([
            ['الرقم' => 'EMP-001', 'الاسم' => 'خالد', 'القسم' => 'الإدارة', 'الوظيفة' => 'محاسب', 'الهاتف' => '0550', 'تاريخ التعيين' => '2026-01-01', 'الراتب الأساسي' => '5000', 'البدلات' => '500', 'نوع العقد' => 'full_time', 'الحالة' => 'active'],
        ]);

        $response = $this->actingAs($this->user)->post('/module-imports/hr/import', [
            'file' => $file,
        ]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(1, $result['created']);

        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP-001', 'name' => 'خالد']);
    }

    public function test_import_skips_rows_without_required_fields(): void
    {
        $file = $this->createExcelFromRows([
            ['الاسم' => '', 'التخصص' => 'lab', 'الهاتف' => '0500', 'نوع الأتعاب' => 'fixed', 'قيمة الأتعاب' => '500', 'نشط' => 'نعم'],
        ]);

        $response = $this->actingAs($this->user)->post('/module-imports/doctors/import', [
            'file' => $file,
        ]);

        $response->assertSessionHas('importResult');
        $result = session('importResult');
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
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
