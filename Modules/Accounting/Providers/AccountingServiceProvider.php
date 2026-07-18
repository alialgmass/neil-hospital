<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Repositories\Contracts\JournalRepositoryInterface;
use Modules\Accounting\Repositories\Contracts\TreasuryRepositoryInterface;
use Modules\Accounting\Repositories\JournalRepository;
use Modules\Accounting\Repositories\TreasuryRepository;

class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TreasuryRepositoryInterface::class, TreasuryRepository::class);
        $this->app->bind(JournalRepositoryInterface::class, JournalRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
