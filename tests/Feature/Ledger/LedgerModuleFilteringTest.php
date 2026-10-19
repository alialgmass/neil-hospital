<?php

namespace Tests\Feature\Ledger;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerModuleFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $cash;

    private Account $clinicRevenue;

    private Account $lasikRevenue;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'reports.financial', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo('reports.financial');
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->cash = Account::create(['code' => '1010', 'name' => 'الخزنة', 'group' => 'assets', 'nature' => 'debit', 'is_active' => true]);
        $this->clinicRevenue = Account::create(['code' => '4010', 'name' => 'إيرادات العيادة', 'group' => 'revenues', 'nature' => 'credit', 'is_active' => true]);
        $this->lasikRevenue = Account::create(['code' => '4040', 'name' => 'إيرادات الليزك', 'group' => 'revenues', 'nature' => 'credit', 'is_active' => true]);

        JournalEntry::create([
            'date' => today(),
            'description' => 'كشف عيادة',
            'debit_account_id' => $this->cash->id,
            'credit_account_id' => $this->clinicRevenue->id,
            'amount' => 100,
        ]);

        JournalEntry::create([
            'date' => today(),
            'description' => 'جلسة ليزك',
            'debit_account_id' => $this->cash->id,
            'credit_account_id' => $this->lasikRevenue->id,
            'amount' => 200,
        ]);
    }

    public function test_trial_balance_hides_accounts_of_disabled_module(): void
    {
        // Regression test: LedgerService::trialBalance() queried all active
        // accounts directly and never checked SystemModule::isEnabled(), so
        // disabling Lasik/Laser in settings had no effect on this report even
        // though Chart of Accounts already filtered them out via AccountService.
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/ledger/trial-balance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rows', fn ($rows) => ! collect($rows)->pluck('code')->contains('4040')
                && collect($rows)->pluck('code')->contains('4010')));
    }

    public function test_trial_balance_shows_all_accounts_when_no_module_disabled(): void
    {
        $response = $this->actingAs($this->user)->get('/ledger/trial-balance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rows', fn ($rows) => collect($rows)->pluck('code')->contains('4040')
                && collect($rows)->pluck('code')->contains('4010')));
    }

    public function test_income_statement_hides_accounts_of_disabled_module(): void
    {
        Setting::setValue(SystemModule::Lasik->settingKey(), 'false', 'modules');

        $response = $this->actingAs($this->user)->get('/ledger/income-statement');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('statement.revenues', fn ($rows) => ! collect($rows)->pluck('code')->contains('4040')
                && collect($rows)->pluck('code')->contains('4010')));
    }
}
