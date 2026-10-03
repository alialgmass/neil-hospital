<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Actions\AutoPostDepreciationAction;

class PostMonthlyDepreciationCommand extends Command
{
    protected $signature = 'accounting:post-depreciation {--period= : YYYY-MM, defaults to the current month}';

    protected $description = 'Post this month\'s straight-line depreciation for every active fixed asset';

    public function handle(AutoPostDepreciationAction $action): int
    {
        $period = $this->option('period');
        $posted = $action->execute($period);

        $this->info("Posted depreciation for {$posted} asset(s)".($period ? " ({$period})" : ' (current month)').'.');

        return self::SUCCESS;
    }
}
