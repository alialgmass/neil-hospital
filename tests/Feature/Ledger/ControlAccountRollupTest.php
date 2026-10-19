<?php

namespace Tests\Feature\Ledger;

use App\Enums\Department;
use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\AutoPostDoctorDuesAction;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\AccountService;
use Modules\Accounting\Services\BalanceSheetService;
use Modules\Accounting\Services\LedgerService;
use Modules\Doctor\Models\Doctor;
use Tests\TestCase;

/**
 * A control account (2010 / 2020 / 2030) is never posted to: wherever it is
 * reported, its balance is the sum of its sub-ledgers.
 */
class ControlAccountRollupTest extends TestCase
{
    use RefreshDatabase;

    private Account $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);

        $dues = app(AutoPostDoctorDuesAction::class);
        foreach (['د. احمد البحار' => 300, 'د. طبيب جديد' => 200] as $name => $amount) {
            $doctor = Doctor::create(['name' => $name, 'fee_type' => 'percentage', 'fee_value' => 50]);
            $dues->execute(Department::Clinic, $amount, $doctor->id, $name, 'P-1', '2026-05-03');
        }

        $this->control = Account::where('code', '2010')->firstOrFail();
    }

    public function test_trial_balance_rolls_subledgers_into_the_control_row_and_foots_on_leaves(): void
    {
        $rows = collect(app(LedgerService::class)->trialBalance())->keyBy('code');

        $this->assertTrue($rows['2010']['is_group']);
        $this->assertEquals(500.0, $rows['2010']['balance']);
        $this->assertEquals(300.0, $rows['2201']['balance']);
        $this->assertEquals(200.0, $rows['2236']['balance']);
        $this->assertFalse($rows->has('2202'), 'idle sub-ledgers are not listed');

        $leaves = $rows->reject(fn ($row) => $row['is_group']);
        $this->assertEquals($leaves->sum('debits'), $leaves->sum('credits'));
    }

    public function test_chart_of_accounts_shows_the_control_total(): void
    {
        $control = app(AccountService::class)->all()->firstWhere('code', '2010');

        $this->assertTrue($control->is_group);
        $this->assertEquals(500.0, $control->rollup_balance);
        $this->assertEquals(0.0, (float) $control->balance);
    }

    public function test_balance_sheet_shows_the_control_account_not_each_doctor(): void
    {
        $liabilities = collect(app(BalanceSheetService::class)->get()['liabilities'])->keyBy('code');

        $this->assertEquals(500.0, $liabilities['2010']['balance']);
        $this->assertFalse($liabilities->has('2201'));
    }

    public function test_control_account_statement_lists_every_subledger_movement(): void
    {
        $statement = app(LedgerService::class)->accountStatement($this->control->id);

        $this->assertCount(2, $statement['statement']);
        $this->assertEquals(500.0, end($statement['statement'])['balance']);
    }
}
