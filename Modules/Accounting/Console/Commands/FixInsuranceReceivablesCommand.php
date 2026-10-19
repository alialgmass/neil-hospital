<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Services\AlignmentLog;
use Modules\Accounting\Services\InsuranceReceivableAccountResolver;

class FixInsuranceReceivablesCommand extends Command
{
    protected $signature = 'accounting:fix-insurance-receivables';

    protected $description = 'Give every insurance company its own receivable account and move claims posted to another company\'s account';

    public function handle(InsuranceReceivableAccountResolver $resolver, AlignmentLog $log): int
    {
        $report = DB::transaction(function () use ($resolver, $log) {
            $report = $resolver->repairMismatched();
            $log->recomputeBalances();

            return $report;
        });

        if ($report === []) {
            $this->info('كل شركة تأمين على حسابها الصحيح.');

            return self::SUCCESS;
        }

        $this->table(['الشركة', 'من حساب', 'إلى حساب', 'قيود منقولة'], array_map('array_values', $report));

        return self::SUCCESS;
    }
}
