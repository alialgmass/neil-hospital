<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\InsuranceReceivableAccountResolver;
use Modules\Insurance\Models\InsuranceCompany;
use Tests\TestCase;

class InsuranceReceivableAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    private function accountCode(string $companyId): string
    {
        return (string) Account::find(app(InsuranceReceivableAccountResolver::class)->resolve($companyId))->code;
    }

    public function test_company_matches_its_guide_account_ignoring_word_order(): void
    {
        $company = InsuranceCompany::create(['name' => 'جولد ميد', 'status' => 'active']);

        $this->assertSame('1044', $this->accountCode($company->id)); // ميد جولد
    }

    public function test_unknown_company_gets_its_own_new_account_not_another_companys(): void
    {
        $first = InsuranceCompany::create(['name' => 'شركة جديدة أ', 'status' => 'active']);
        $second = InsuranceCompany::create(['name' => 'شركة جديدة ب', 'status' => 'active']);

        $this->assertSame('1061', $this->accountCode($first->id));
        $this->assertSame('1062', $this->accountCode($second->id));
        $this->assertSame('ذمم تأمين — شركة جديدة أ', Account::where('code', '1061')->value('name'));
    }

    public function test_repair_moves_a_company_off_another_companys_account(): void
    {
        $company = InsuranceCompany::create(['name' => 'شركة جديدة أ', 'status' => 'active']);
        $healthInsurance = Account::where('code', '1032')->firstOrFail();
        $company->forceFill(['receivable_account_id' => $healthInsurance->id])->save();

        $report = app(InsuranceReceivableAccountResolver::class)->repairMismatched();

        $this->assertCount(1, $report);
        $this->assertSame('1032', $report[0]['from']);
        $this->assertSame('1061', $report[0]['to']);
        $this->assertSame('1061', $this->accountCode($company->id));
    }

    public function test_repair_leaves_correctly_assigned_companies_alone(): void
    {
        $company = InsuranceCompany::create(['name' => 'التأمين الصحي', 'status' => 'active']);
        $this->accountCode($company->id);

        $this->assertSame([], app(InsuranceReceivableAccountResolver::class)->repairMismatched());
        $this->assertSame('1032', DB::table('accounts')->where('id', $company->fresh()->receivable_account_id)->value('code'));
    }
}
