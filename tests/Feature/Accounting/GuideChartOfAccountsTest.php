<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\AccountCode;
use Modules\Accounting\Enums\Subledger;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\GuideChart;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Accounting\Services\JournalService;
use Modules\Accounting\Services\SubledgerAccountResolver;
use Modules\Doctor\Models\Doctor;
use Tests\TestCase;

/**
 * الدليل المحاسبي v2.0 — the seeded chart is the guide's, exactly, and the
 * control accounts 2010 / 2020 / 2030 only ever move through their
 * per-party sub-ledgers.
 */
class GuideChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public function test_seeder_builds_the_guide_tree_exactly(): void
    {
        $check = app(GuideConformanceService::class)->run()['chart'];

        $this->assertTrue($check['passed'], implode("\n", $check['details']));
        $this->assertSame(count(GuideChart::accounts()), Account::where('is_active', true)->count());
    }

    public function test_named_subledgers_exist_with_the_guides_codes_and_names(): void
    {
        $this->assertSame('دائنون — دكتور احمد البحار', Account::where('code', '2201')->value('name'));
        $this->assertSame('دائنون — دكتوره سحر نشات', Account::where('code', '2235')->value('name'));
        $this->assertSame('دائنون — عمار مستلزمات', Account::where('code', '2313')->value('name'));
        $this->assertSame('دائنون — الأدوية الدولية', Account::where('code', '2324')->value('name'));
        $this->assertSame('مستحق راتب — اسامة', Account::where('code', '2401')->value('name'));
        $this->assertSame('مستحق راتب — مايكل', Account::where('code', '2416')->value('name'));

        $this->assertSame(35 + 1, Account::where('parent_id', Account::where('code', '2010')->value('id'))->count());
        $this->assertSame(24 + 1, Account::where('parent_id', Account::where('code', '2020')->value('id'))->count());
        $this->assertSame(16 + 1, Account::where('parent_id', Account::where('code', '2030')->value('id'))->count());
    }

    public function test_control_and_group_accounts_are_not_postable(): void
    {
        foreach (['1000', '1030', '1050', '5260', '2010', '2020', '2030', '1100', '2000', '4000', '4100', '4200', '4900', '5000', '5100', '5200'] as $code) {
            $this->assertFalse((bool) Account::where('code', $code)->value('is_postable'), "{$code} must be non-postable");
        }
    }

    public function test_out_of_guide_accounts_are_never_postable(): void
    {
        // Older migrations leave some legacy rows behind; the seeder retires any that never moved.
        foreach (['4065', '4080', '5115', '4125', '2035'] as $code) {
            $this->assertFalse(Account::where('code', $code)->where('is_active', true)->exists(), "{$code} must not be active");
        }

        $this->assertTrue(app(GuideConformanceService::class)->run()['out_of_guide']['passed']);
    }

    public function test_posting_directly_to_a_control_account_is_refused(): void
    {
        $this->expectException(AccountingException::class);

        app(JournalService::class)->record([
            'date' => '2026-05-03',
            'description' => 'x',
            'debit_account_id' => Account::where('code', AccountCode::DOCTOR_CLINIC_EXPENSE->value)->value('id'),
            'credit_account_id' => Account::where('code', AccountCode::DOCTOR_PAYABLE->value)->value('id'),
            'amount' => 100,
        ]);
    }

    public function test_doctor_is_linked_to_the_guide_account_matching_their_name(): void
    {
        $doctor = Doctor::create(['name' => 'د. شريف صابر', 'fee_type' => 'percentage', 'fee_value' => 50]);

        $accountId = app(SubledgerAccountResolver::class)->forDoctor($doctor->id);

        $this->assertSame('2213', Account::whereKey($accountId)->value('code'));
        $this->assertSame($accountId, DB::table('doctors')->where('id', $doctor->id)->value('payable_account_id'));
        $this->assertSame($accountId, app(SubledgerAccountResolver::class)->forDoctor($doctor->id));
    }

    public function test_a_party_not_in_the_guide_gets_the_next_free_code_in_its_range(): void
    {
        $doctor = Doctor::create(['name' => 'د. طبيب جديد', 'fee_type' => 'percentage', 'fee_value' => 50]);

        $account = Account::findOrFail(app(SubledgerAccountResolver::class)->forDoctor($doctor->id));

        $this->assertSame('2236', $account->code);
        $this->assertSame('دائنون — د. طبيب جديد', $account->name);
        $this->assertSame('2010', $account->parent->code);
        $this->assertTrue($account->is_postable);
    }

    public function test_employee_and_supplier_resolve_inside_their_own_ranges(): void
    {
        $employeeId = (string) Str::ulid();
        DB::table('employees')->insert(['id' => $employeeId, 'employee_no' => 'E-1', 'name' => 'مروة', 'dept' => 'admin', 'position' => 'محاسب', 'hire_date' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $supplierId = (string) Str::ulid();
        DB::table('suppliers')->insert(['id' => $supplierId, 'name' => 'مورد غير مدرج', 'created_at' => now(), 'updated_at' => now()]);

        $resolver = app(SubledgerAccountResolver::class);

        $this->assertSame('2413', Account::whereKey($resolver->resolve(Subledger::Employee, $employeeId))->value('code'));
        $this->assertSame('2325', Account::whereKey($resolver->resolve(Subledger::Supplier, $supplierId))->value('code'));
    }

    public function test_arabic_name_normalization_ignores_titles_and_letter_variants(): void
    {
        $this->assertSame(
            SubledgerAccountResolver::normalizeName('دائنون — دكتوره رضوى سامى مالك'),
            SubledgerAccountResolver::normalizeName('د. رضوي سامي مالك'),
        );
        $this->assertSame(
            SubledgerAccountResolver::normalizeName('دائنون — دكتور احمد عبداللاه'),
            SubledgerAccountResolver::normalizeName('دكتور أحمد عبد اللاه'),
        );
    }
}
