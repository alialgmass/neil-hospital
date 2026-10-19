<?php

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Admin\Console\Commands\PurgeDataBeforeDateCommand;
use Modules\Admin\Repositories\Contracts\UserRepositoryInterface;
use Modules\Admin\Repositories\UserRepository;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeDataBeforeDateCommand::class]);
        }
    }
}
