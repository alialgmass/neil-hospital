<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Exceptions\AccountingException;
use Modules\Accounting\Services\OpeningBalanceService;

/**
 * Posts the opening-balance journal from a JSON file:
 *
 *   {"date": "2026-01-01", "balances": {"3010": 150000, "1010": 50000, "1051": 100000}}
 *
 * One natural-side balance per postable account (sub-ledgers 22xx/23xx/24xx,
 * never the 2010/2020/2030 controls); the set must foot.
 */
class PostOpeningBalancesCommand extends Command
{
    protected $signature = 'accounting:opening-balance
        {file : Path to the JSON file with {"date": "YYYY-MM-DD", "balances": {"code": amount}}}
        {--dry-run : Validate and show the journal without posting}';

    protected $description = 'Post the opening-balance journal (الدليل المحاسبي v2.0 §3.1)';

    public function handle(OpeningBalanceService $openingBalances): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload) || empty($payload['date']) || ! is_array($payload['balances'] ?? null)) {
            $this->error('Invalid file: expected {"date": "YYYY-MM-DD", "balances": {"code": amount}}');

            return self::FAILURE;
        }

        try {
            $lines = $this->option('dry-run')
                ? $openingBalances->preview($payload['date'], $payload['balances'])
                : $openingBalances->post($payload['date'], $payload['balances']);
        } catch (AccountingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['الكود', 'الحساب', 'مدين', 'دائن'], array_map(fn ($line) => [
            $line['code'], $line['name'], $line['debit'] ? number_format($line['debit'], 2) : '', $line['credit'] ? number_format($line['credit'], 2) : '',
        ], $lines));

        $this->info($this->option('dry-run') ? 'Dry run — nothing posted.' : 'Opening balances posted.');

        return self::SUCCESS;
    }
}
