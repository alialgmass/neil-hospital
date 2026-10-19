<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Services\GuideConformanceService;

class VerifyGuideConformanceCommand extends Command
{
    protected $signature = 'accounting:verify-guide {--inventory : Also print the inventory relief vs cost reconciliation}';

    protected $description = 'Check the ledger against الدليل المحاسبي v2.0 (chart, control accounts, insurance cycle, inventory costing, trial balance)';

    public function handle(GuideConformanceService $conformance): int
    {
        $failed = 0;

        foreach ($conformance->run() as $check) {
            $this->line(($check['passed'] ? '<info>✔</info> ' : '<error>✘</error> ').$check['label']);

            foreach (array_slice($check['details'], 0, 25) as $detail) {
                $this->line("    - {$detail}");
            }

            if (count($check['details']) > 25) {
                $this->line('    … +'.(count($check['details']) - 25).' more');
            }

            $failed += $check['passed'] ? 0 : 1;
        }

        if ($this->option('inventory')) {
            $reconciliation = $conformance->inventoryReconciliation();

            $this->newLine();
            $this->info('تسوية المخزون مع التكلفة');
            $this->table(['البند', 'المبلغ'], [
                ['إجمالي الدائن على المخزون (1051–1053)', number_format($reconciliation['relief_total'], 2)],
                ['منه: صرف/استهلاك', number_format($reconciliation['consumption_relief'], 2)],
                ['منه: مرتجعات مشتريات / عكس فواتير', number_format($reconciliation['non_consumption_relief'], 2)],
                ['التكلفة المسجّلة مقابل الاستهلاك', number_format($reconciliation['consumption_cost'], 2)],
            ]);
            $this->table(['الحساب المدين', 'المبلغ'], collect($reconciliation['by_debit_account'])
                ->map(fn ($amount, $code) => [$code, number_format($amount, 2)])->values()->all());
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
