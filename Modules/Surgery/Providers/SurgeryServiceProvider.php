<?php

namespace Modules\Surgery\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Surgery\Repositories\Contracts\SurgeryRepositoryInterface;
use Modules\Surgery\Repositories\SurgeryRepository;

class SurgeryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SurgeryRepositoryInterface::class, SurgeryRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
