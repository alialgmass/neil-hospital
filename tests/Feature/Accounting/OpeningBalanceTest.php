<?php

namespace Tests\Feature\Accounting;

use Database\Seeders\AccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\GuideConformanceService;
use Modules\Accounting\Services\OpeningBalanceService;
use Tests\TestCase;

/**
 * Guide §3.1 — the opening-balance journal: capital, fixed assets net of
 * accumulated depreciation, bank and brought-forward payables, entered as
 * natural-side balances that must foot.
 */
class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, float> */
    private const BALANCES = [
        '3010' => 400000,  // capital
        '1010' => 50000,   // cash
        '1020' => 120000,  // bank
        '1051' => 100000,  // medical supplies
        '1130' => 300000,  // medical equipment …
        '1131' => 60000,   // … less accumulated depreciation (credit balance)
        '2313' => 40000,   // owed to عمار مستلزمات
        '3020' => 70000,   // retained earnings
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountsSeeder::class);
    }

    public function test_posts_every_balance_and_the_ledger_foots(): void
    {
        app(OpeningBalanceService::class)->post('2026-01-01', self::BALANCES);

        foreach (self::BALANCES as $code => $balance) {
            $this->assertEquals($balance, (float) Account::where('code', $code)->value('balance'), "opening balance of {$code}");
        }

        $this->assertSame(7, JournalEntry::where('source', 'opening_balance')->count());

        $check = app(GuideConformanceService::class)->run()['trial_balance'];
        $this->assertTrue($check['passed'], implode("\n", $check['details']));
    }

    public function test_an_unbalanced_set_is_refused(): void
    {
        $this->expectException(AccountingException::class);
        $this->expectExceptionMessage('غير متوازنة');

        app(OpeningBalanceService::class)->post('2026-01-01', ['1010' => 50000, '3010' => 40000]);
    }

    public function test_a_control_account_balance_must_go_on_its_subledgers(): void
    {
        $this->expectException(AccountingException::class);

        app(OpeningBalanceService::class)->post('2026-01-01', ['1051' => 40000, '2020' => 40000]);
    }

    public function test_it_cannot_be_posted_twice(): void
    {
        app(OpeningBalanceService::class)->post('2026-01-01', ['1010' => 1000, '3010' => 1000]);

        $this->expectException(AccountingException::class);

        app(OpeningBalanceService::class)->post('2026-01-01', ['1010' => 1000, '3010' => 1000]);
    }

    public function test_the_command_dry_run_validates_without_posting(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ob').'.json';
        file_put_contents($file, json_encode(['date' => '2026-01-01', 'balances' => self::BALANCES]));

        $this->artisan('accounting:opening-balance', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, JournalEntry::count());

        $this->artisan('accounting:opening-balance', ['file' => $file])->assertSuccessful();
        $this->assertSame(7, JournalEntry::count());

        @unlink($file);
    }
}
